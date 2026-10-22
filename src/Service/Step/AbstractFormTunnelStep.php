<?php

namespace Wexample\SymfonyTunnels\Service\Step;

use Symfony\Component\Form\FormInterface;
use Wexample\SymfonyForms\Service\FormProcessor\AbstractFormProcessor;
use Wexample\SymfonyTunnels\Class\TunnelCursor;
use Wexample\SymfonyTunnels\Enum\TunnelStepCompleteStrategy;

/**
 * A step showing a form. The form is an ordinary symfony-forms processor, which
 * does what a valid submission means for the application; the step only says
 * where the tunnel goes next.
 */
abstract class AbstractFormTunnelStep extends AbstractTunnelStep
{
    abstract public function getFormProcessor(TunnelCursor $cursor): AbstractFormProcessor;

    /**
     * Such a step is done when its form is, not when it is displayed.
     */
    public function completeStrategy(): TunnelStepCompleteStrategy
    {
        return TunnelStepCompleteStrategy::MANUAL;
    }

    /**
     * The translation key of the next button, which submits the form: a step
     * asking for a payment says so on it.
     */
    public function buildSubmitLabel(TunnelCursor $cursor): string
    {
        return 'WexampleSymfonyTunnelsBundle.common.tunnel::button.next';
    }

    /**
     * The data the form starts from.
     */
    public function buildFormData(TunnelCursor $cursor): mixed
    {
        return null;
    }

    /**
     * Called once the processor has handled a valid submission.
     *
     * @return TunnelCursor|null where the visitor goes next; null keeps them on
     *                           the step, waiting for something else to move on
     */
    public function onFormValid(
        FormInterface $form,
        TunnelCursor $cursor,
    ): ?TunnelCursor {
        $next = $cursor->manager->selectNextCursor($cursor);
        $cursor->setComplete($next);

        return $next;
    }
}
