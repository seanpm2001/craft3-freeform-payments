<?php

namespace Solspace\FreeformPayments\Library\ElementHookHandlers;

use yii\base\Event;
use Solspace\Freeform\Events\Forms\SaveEvent;
use Solspace\Freeform\Services\FormsService;
use Solspace\Freeform\Library\Composer\Components\Properties\PaymentProperties;

class FormHookHandler
{
    /**
     * Register hooks on Submission element handled by this class
     *
     * @return void
     */
    static public function registerHooks()
    {
        Event::on(
            FormsService::class,
            FormsService::EVENT_BEFORE_SAVE,
            array(self::class, 'validate')
        );
    }

    /**
     * Unregisters all previously registered hooks
     *
     * @return void
     */
    static public function unregisterHooks()
    {
        Event::off(
            FormsService::class,
            FormsService::EVENT_BEFORE_SAVE,
            array(self::class, 'validate')
        );
    }

    /**
     * Handler for SaveEvent from Form model
     *
     * @param SaveEvent $event
     *
     * @return void
     */
    static public function validate(SaveEvent $event)
    {
        $formsService = $event->sender;

        $formModel = $event->getModel();
        $paymentFields = $formModel->getLayout()->getPaymentFields();
        if (!$paymentFields) {
            return;
        }
        $paymentField = $paymentFields[0];

        $paymentProperties = $formModel->getComposer()->getForm()->getPaymentProperties();

        $attribute = $paymentField->getHandle();
        if (!$paymentProperties->getIntegrationId()) {
            $formModel->addError($attribute, 'Payment gateway is not configured!');
        }

        $paymentType = $paymentProperties->getPaymentType();
        if (!$paymentType) {
            $formModel->addError($attribute, 'Payment type is not configured!');
        }

        $paymentFieldMapping = $paymentProperties->getPaymentFieldMapping();
        if ($paymentType != PaymentProperties::PAYMENT_TYPE_PREDEFINED_SUBSCRIPTION) {
            if (!$paymentProperties->getAmount()
                && !isset($paymentFieldMapping[PaymentProperties::FIELD_AMOUNT])
            ) {
                $formModel->addError($attribute, 'Payment amount is not configured!');
            }
        } else {
            //if there are no plans to select from and form is not saved
            //user will end up without ability to create plan
            //so we skip this validation if form is not yet saved
            if ($formModel->id
                && !$paymentProperties->getPlan()
                && !isset($paymentFieldMapping[PaymentProperties::FIELD_PLAN])
            ) {
                $formModel->addError($attribute, 'Subscription plan is not configured!');
            }
        }
    }
}
