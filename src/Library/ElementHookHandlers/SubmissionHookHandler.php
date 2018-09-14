<?php

namespace Solspace\FreeformPayments\Library\ElementHookHandlers;

use craft\events\RegisterElementActionsEvent;
use craft\events\RegisterElementTableAttributesEvent;
use craft\events\SetElementTableAttributeHtmlEvent;
use craft\helpers\ElementHelper;
use Solspace\Freeform\Elements\Submission;
use Solspace\Freeform\Freeform;
use Solspace\Freeform\Library\Composer\Components\Fields\Interfaces\PaymentInterface as FieldPaymentInterface;
use Solspace\Freeform\Library\Payments\PaymentInterface;
use Solspace\FreeformPayments\Elements\Actions\FixPaymentsAction;
use Solspace\FreeformPayments\FreeformPayments;
use yii\base\Event;

class SubmissionHookHandler
{
    const COLUMN_STATUS = 'paymentStatus';
    const COLUMN_TYPE   = 'paymentType';
    const COLUMN_CARD   = 'paymentCard';

    const ATTRIBUTES = [
        self::COLUMN_TYPE   => 'Payment Type',
        self::COLUMN_STATUS => 'Payment Status',
        self::COLUMN_CARD   => 'Payment Card',
    ];

    const TEMPLATE_FOLDER = 'freeform-payments/_components/fields';

    /**
     * Register hooks on Submission element handled by this class
     *
     * @return void
     */
    public static function registerHooks()
    {
        Event::on(
            Submission::class,
            Submission::EVENT_REGISTER_TABLE_ATTRIBUTES,
            [self::class, 'injectTableColumns']
        );

        Event::on(
            Submission::class,
            Submission::EVENT_SET_TABLE_ATTRIBUTE_HTML,
            [self::class, 'renderTableColumns']
        );

        Event::on(
            Submission::class,
            Submission::EVENT_REGISTER_SORT_OPTIONS,
            [self::class, 'removePaymentFromSortOptions']
        );

        Event::on(
            Submission::class,
            Submission::EVENT_REGISTER_ACTIONS,
            [self::class, 'registerPaymentActions']
        );
    }

    /**
     * Unregisters all previously registered hooks
     *
     * @return void
     */
    public static function unregisterHooks()
    {
        Event::off(
            Submission::class,
            Submission::EVENT_REGISTER_TABLE_ATTRIBUTES
        );

        Event::off(
            Submission::class,
            Submission::EVENT_SET_TABLE_ATTRIBUTE_HTML
        );

        Event::off(
            Submission::class,
            Submission::EVENT_REGISTER_SORT_OPTIONS
        );

        Event::off(
            Submission::class,
            Submission::EVENT_REGISTER_ACTIONS
        );
    }

    /**
     * Handler for RegisterElementTableAttributesEvent from Submission element
     *
     * @param SetElementTableAttributeHtmlEvent $event
     *
     * @return void
     */
    public static function injectTableColumns(RegisterElementTableAttributesEvent $event)
    {
        foreach (self::ATTRIBUTES as $attribute => $label) {
            $event->tableAttributes[$attribute] = ['label' => FreeformPayments::t($label)];
        }
    }

    /**
     * Handler for SetElementTableAttributeHtmlEvent from Submission element
     *
     * @param SetElementTableAttributeHtmlEvent $event
     *
     * @return void
     */
    public static function renderTableColumns(SetElementTableAttributeHtmlEvent $event)
    {
        $html      = null;
        $attribute = $event->attribute;

        if (in_array($attribute, array_keys(self::ATTRIBUTES))) {
            $payment = self::getPayment($event);
            $html    = self::renderColumn($attribute, $payment);
        } else if ($event->sender->$attribute) {
            $field = $event->sender->$attribute;
            if ($field instanceof FieldPaymentInterface) {
                $payment = self::getPayment($event);
                $html    = self::renderColumn(self::COLUMN_TYPE, $payment);
            }
        }

        if (!$html) {
            return;
        }

        $event->html    = $html;
        $event->handled = true;
    }

    /**
     * Returns html for submission payments column
     *
     * @param string           $attribute
     * @param PaymentInterface $payment
     *
     * @return string
     */
    public static function renderColumn(string $attribute, PaymentInterface $payment = null): string
    {
        $template = self::getTemplatePath($attribute);

        return \Craft::$app->view->renderTemplate($template, ['payment' => $payment]);
    }

    /**
     * Generates template path for submission payment column
     *
     * @param string $attribute
     *
     * @return string
     */
    public static function getTemplatePath(string $attribute): string
    {
        return self::TEMPLATE_FOLDER . '/' . $attribute . '.html';
    }

    /**
     * Returns Payment for a submission event
     *
     * @param Event $event
     *
     * @return PaymentInterface
     */
    public static function getPayment(Event $event)
    {
        $submission   = $event->sender;
        $submissionId = $submission->getId();

        $payment = FreeformPayments::getInstance()->subscriptions->getBySubmissionId($submissionId);
        if (!$payment) {
            $payment = FreeformPayments::getInstance()->payments->getBySubmissionId($submissionId);
        }

        return $payment;
    }

    public static function removePaymentFromSortOptions(Event $event)
    {
        $injectedColumns = array_keys(self::ATTRIBUTES);
        $sortOptions     = $event->sortOptions;

        $event->sortOptions = array_reduce(
            array_keys($sortOptions),
            function ($carry, $key) use ($injectedColumns, $sortOptions) {
                if (!in_array($key, $injectedColumns)) {
                    $carry[$key] = $sortOptions[$key];
                }

                return $carry;
            },
            []
        );
    }

    public static function registerPaymentActions(RegisterElementActionsEvent $event)
    {
        // show action only for forms with payments configured
        $source = ElementHelper::findSource(Submission::class, $event->source);
        if ($source['key'] == '*') {
            return;
        }
        $form          = Freeform::getInstance()->forms->getFormByHandle($source['data']['handle']);
        $paymentFields = $form->getLayout()->getPaymentFields();
        if (count($paymentFields) > 0) {
            $event->actions[] = FixPaymentsAction::class;
        }
    }
}
