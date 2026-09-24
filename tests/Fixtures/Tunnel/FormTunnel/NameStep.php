<?php

namespace Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\FormTunnel;

use Symfony\Component\Form\FormInterface;
use Wexample\SymfonyForms\Service\FormProcessor\AbstractFormProcessor;
use Wexample\SymfonyTunnels\Class\TunnelCursor;
use Wexample\SymfonyTunnels\Service\Step\AbstractFormTunnelStep;
use Wexample\SymfonyTunnels\Tests\Fixtures\Service\FormProcessor\TunnelTestFormProcessor;

/**
 * Asks for a name, keeps it, and prefills the form with it when the visitor
 * comes back.
 */
class NameStep extends AbstractFormTunnelStep
{
    public const string VARIABLE_NAME = 'name';

    public function __construct(
        private readonly TunnelTestFormProcessor $formProcessor,
        private readonly ThanksStep $thanksStep,
    ) {
    }

    public static function getName(): string
    {
        return 'name';
    }

    public function getFormProcessor(TunnelCursor $cursor): AbstractFormProcessor
    {
        return $this->formProcessor;
    }

    public function buildFormData(TunnelCursor $cursor): mixed
    {
        return ['name' => $cursor->getVariableValue(self::VARIABLE_NAME)];
    }

    public function onFormValid(FormInterface $form, TunnelCursor $cursor): ?TunnelCursor
    {
        $cursor->setVariableValue(self::VARIABLE_NAME, $form->getData()['name']);

        return parent::onFormValid($form, $cursor);
    }

    public function getAllowedNextSteps(TunnelCursor $cursor): array
    {
        return [$this->thanksStep];
    }
}
