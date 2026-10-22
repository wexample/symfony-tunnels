<?php

namespace Wexample\SymfonyTunnels\Tests\Fixtures\Service\FormProcessor;

use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Wexample\SymfonyForms\Service\FormProcessor\AbstractFormProcessor;

/**
 * Refuses an empty name by itself: the fixture kernel has no validator.
 */
class TunnelTestFormProcessor extends AbstractFormProcessor
{
    public function formIsValid(FormInterface $form): bool
    {
        if (trim((string) $form->get('name')->getData()) === '') {
            $form->get('name')->addError(new FormError('name.required'));

            return false;
        }

        return parent::formIsValid($form);
    }
}
