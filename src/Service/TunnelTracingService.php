<?php

namespace Wexample\SymfonyTunnels\Service;

use Wexample\SymfonyTunnels\Class\TunnelCursor;
use Wexample\SymfonyTunnels\Helper\TunnelTreeHelper;

/**
 * The tunnel drawn as text, for the console: its tree as declared, and its map
 * with one column per path.
 *
 * Marks carry console style tags; with a session at hand, the current cursor and
 * the completed ones stand out.
 */
class TunnelTracingService
{
    public const string MARK_CURSOR = '●';

    public const string MARK_NO_CURSOR = '|';

    public function traceTree(AbstractTunnelManagerService $tunnel): string
    {
        return $this->traceTreePart($tunnel->createEntrypoint(), 0);
    }

    /**
     * One line per section, one column per path: a mark where the path goes
     * through the section, a bar where it does not.
     */
    public function traceSections(AbstractTunnelManagerService $tunnel): string
    {
        $entrypoint = $tunnel->getEntrypointCursor() ?? $tunnel->createEntrypoint();
        $paths = TunnelTreeHelper::buildPaths($entrypoint);
        $lines = [];

        foreach (TunnelTreeHelper::buildSections($entrypoint) as $section) {
            $line = '';

            foreach (array_keys($paths) as $pathIndex) {
                $cursor = $section->pathCursors[$pathIndex] ?? null;
                $line .= ($cursor ? $this->traceMark($cursor) : self::MARK_NO_CURSOR) . ' ';
            }

            $lines[] = $line . $section->cursor->step::getName()
                . ($section->cursor->options ? ' ' . json_encode($section->cursor->options) : '');
        }

        return implode(PHP_EOL, $lines);
    }

    private function traceTreePart(
        TunnelCursor $cursor,
        int $indent,
    ): string {
        $indentText = str_repeat('  ', $indent);
        $output = $indentText . '> ' . $cursor->step::getName()
            . PHP_EOL . $indentText . '  <comment>' . $cursor . '</comment>';

        foreach ($cursor->next as $next) {
            $output .= PHP_EOL . $this->traceTreePart($next, $indent + 1);
        }

        return $output;
    }

    private function traceMark(TunnelCursor $cursor): string
    {
        if (!$cursor->manager->getSession()) {
            return self::MARK_CURSOR;
        }

        if ($cursor->manager->getCurrentCursor() === $cursor) {
            return '<fg=blue>' . self::MARK_CURSOR . '</>';
        }

        if ($cursor->isComplete()) {
            return '<fg=yellow>' . self::MARK_CURSOR . '</>';
        }

        return self::MARK_CURSOR;
    }
}
