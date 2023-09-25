<?php

namespace Wexample\SymfonyTunnels\Service\FormProcessor\Traits;

use Symfony\Component\Form\FormInterface;
use Wexample\SymfonyTunnels\Service\Step\Traits\FormTunnelStepTrait;
use Wexample\SymfonyTunnels\Service\Step\TunnelStep;

trait TunnelFormProcessorTrait
{
    /**
     * @var TunnelStep|FormTunnelStepTrait|null
     */
    public ?TunnelStep $tunnelStep = null;

    public function onRender(FormInterface $form): void
    {
        $this->getTunnelStep()->onFormRender($form);
    }

    public function onValid(FormInterface $form): void
    {
        $this->getTunnelStep()->onFormValid($form);
    }

    /**
     * @return FormTunnelStepTrait|TunnelStep|null
     */
    public function getTunnelStep(): ?TunnelStep
    {
        return $this->tunnelStep;
    }

    /**
     * @param FormTunnelStepTrait|TunnelStep $tunnelStep
     */
    public function setTunnelStep(TunnelStep $tunnelStep): void
    {
        $this->tunnelStep = $tunnelStep;
    }
}
