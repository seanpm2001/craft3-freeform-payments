<?php

namespace Solspace\FreeformPayments\Fields;

use Solspace\Freeform\Library\Composer\Components\Fields\Interfaces\PaymentInterface;
use Solspace\Freeform\Library\Composer\Components\Fields\TextField;

class CreditCardExpiryField extends TextField
{
    const FIELD_NAME = 'CreditCardExpDate';

    /**
     * @return string
     */
    public function getType(): string
    {
        return self::TYPE_CREDIT_CARD_EXPIRY;
    }

    /**
     * @return bool
     */
    public function isRequired(): bool
    {
        return true;
    }

    /**
     * @return string
     */
    public function getIdAttribute(): string
    {
        return $this->getCustomAttributes()->getId();
    }

    /**
     * Outputs the HTML of input
     *
     * @return string
     */
    protected function getInputHtml(): string
    {
        $attributes  = $this->getCustomAttributes();
        $classString = $attributes->getClass().' '.$this->getInputClassString();
        $handle      = $this->getHandle();
        $id          = $this->getIdAttribute();

        return '<div '
            .$this->getAttributeString('name', $handle)
            .$this->getAttributeString('id', $id)
            .$this->getAttributeString('class', $classString)
            .$this->getAttributeString(
                'placeholder',
                $this->translate($attributes->getPlaceholder() ?: $this->getPlaceholder())
            )
            .$this->getRequiredAttribute()
            .$attributes->getInputAttributesAsString()
            .'></div>';
    }
}
