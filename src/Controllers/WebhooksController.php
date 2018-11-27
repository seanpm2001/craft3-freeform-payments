<?php

namespace Solspace\FreeformPayments\Controllers;

use Solspace\Freeform\Library\Logging\FreeformLogger;
use Solspace\FreeformPayments\FreeformPayments;
use Solspace\FreeformPayments\Integrations\PaymentGateways\Stripe;
use Stripe\Event;
use yii\web\HttpException;

//TODO: create abstract controller
class WebhooksController extends BasePaymentsController
{
    public $enableCsrfValidation = false;

    protected $allowAnonymous = true;

    public function actionStripe()
    {
        $this->requirePostRequest();

        $request       = \Craft::$app->request;
        $payload       = $request->getRawBody();
        $integrationId = $request->getQueryParam('id');

        /** @var Stripe $integration */
        $integration = $this->getPaymentGatewaysService()->getIntegrationObjectById($integrationId);

        if (!$integration) {
            throw new HttpException(400, FreeformPayments::t('Invalid integration'));
        }

        $endpointSecret = $integration->getSettings()[Stripe::SETTING_WEBHOOK_KEY];

        if (!$endpointSecret) {
            throw new HttpException(400, FreeformPayments::t('Integration is not configured properly'));
        }

        $sigHeader = isset($_SERVER['HTTP_STRIPE_SIGNATURE']) ? $_SERVER['HTTP_STRIPE_SIGNATURE'] : '';

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
        } catch (\UnexpectedValueException $e) {
            throw new HttpException(400, FreeformPayments::t('Invalid payload'));
        } catch (\Stripe\Error\SignatureVerification $e) {
            throw new HttpException(400, FreeformPayments::t('Invalid signature'));
        }

        //TODO: implement all notification service call as events?
        //TODO: update payment/subscription status accordingly
        switch ($event->type) {
            case Event::CHARGE_SUCCEEDED:
                $submissionId = $this->getSubmissionIdFromStripeEvent($event, $integration, true);
                if ($submissionId) {
                    $this->getPaymentsNotificationService()->sendChargeSucceeded($submissionId);
                }
                break;
            case Event::CHARGE_FAILED:
                $submissionId = $this->getSubmissionIdFromStripeEvent($event, $integration);
                $this->getPaymentsNotificationService()->sendChargeFailed($submissionId);
                break;
            case Event::CUSTOMER_SUBSCRIPTION_CREATED:
                $submissionId = $this->getSubmissionIdFromStripeEvent($event, $integration);
                $this->getPaymentsNotificationService()->sendSubscriptionCreated($submissionId);
                break;
            case Event::CUSTOMER_SUBSCRIPTION_DELETED:
                $submissionId = $this->getSubmissionIdFromStripeEvent($event, $integration);
                $this->getPaymentsNotificationService()->sendSubscriptionEnded($submissionId);
                break;
            case Event::INVOICE_PAYMENT_SUCCEEDED:
                return '';
                //TODO: fix to correct path
                $submissionId = $event->data->object->lines->data[0]->subscription;
                if (!$submissionId) {
                    throw new HttpException(400, $errorMessage);
                }
                $this->getPaymentsNotificationService()->sendSubscriptionPaymentSucceeded($submissionId);
                break;
            case Event::INVOICE_PAYMENT_FAILED:
                return '';
                //TODO: fix to correct path
                $submissionId = $event->data->object->lines->data[0]->subscription;
                if (!$submissionId) {
                    throw new HttpException(400, $errorMessage);
                }
                $this->getPaymentsNotificationService()->sendSubscriptionPaymentFailed($submissionId);
                break;
            default:
        }

        return '';
    }

    /**
     * @param Event  $event
     * @param Stripe $integration
     *
     * @return mixed
     * @throws HttpException
     */
    private function getSubmissionIdFromStripeEvent(Event $event, Stripe $integration, bool $suppressError = false)
    {
        $submissionId = $event->data->object->metadata->submission;
        if ($submissionId) {
            return $submissionId;
        }

        $subscriptionId = $event->data->object->source->metadata->subscription;
        if ($subscriptionId) {
            $subscription = $integration->getSubscriptionDetails($subscriptionId);

            if (isset($subscription['metadata']['submission'])) {
                return $subscription['metadata']['submission'];
            }
        }

        if ($suppressError) {
            return null;
        }

        $errorMessage = FreeformPayments::t('Event is not linked to freeform submission');

        $this->getLoggerService()
            ->getLogger(FreeformLogger::STRIPE)
            ->error(
                $errorMessage,
                ['stripe_event' => json_encode($event)]
            );

        throw new HttpException(400, $errorMessage);
    }
}
