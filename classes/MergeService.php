<?php
/**
 * Copyright (C) 2017-2024 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    thirty bees <modules@thirtybees.com>
 * @copyright 2017-2024 thirty bees
 * @license   Academic Free License (AFL 3.0)
 */

namespace CoreUpdater;

class MergeService
{
    const MATRIX_FALLBACK_LIMIT = 120;

    /**
     * Build a three-way, line-based merge preview for text content.
     *
     * @param string $baseContent
     * @param string $localContent
     * @param string $incomingContent
     *
     * @return array
     */
    public static function buildMergePreview($baseContent, $localContent, $incomingContent)
    {
        $lineEnding = static::detectLineEnding($localContent, $incomingContent, $baseContent);
        $base = static::splitLines($baseContent);
        $local = static::splitLines($localContent);
        $incoming = static::splitLines($incomingContent);

        $localChanges = static::buildChanges($base, $local);
        $incomingChanges = static::buildChanges($base, $incoming);

        $chunks = [];
        $merged = [];
        $choiceCount = 0;
        $conflictCount = 0;
        $position = 0;
        $localIndex = 0;
        $incomingIndex = 0;
        $chunkId = 1;

        while ($localIndex < count($localChanges) || $incomingIndex < count($incomingChanges)) {
            $localChange = $localIndex < count($localChanges) ? $localChanges[$localIndex] : null;
            $incomingChange = $incomingIndex < count($incomingChanges) ? $incomingChanges[$incomingIndex] : null;

            if ($localChange && (! $incomingChange || (! static::changesOverlap($localChange, $incomingChange) && static::changeBefore($localChange, $incomingChange)))) {
                static::appendContextChunk($chunks, $merged, $base, $position, $localChange['start']);
                $localVariant = static::applyChanges($base, $localChange['start'], $localChange['end'], [$localChange]);
                $incomingVariant = array_slice($base, $localChange['start'], $localChange['end'] - $localChange['start']);
                static::appendChoiceChunk($chunks, $merged, [
                    'id' => 'chunk-' . $chunkId++,
                    'state' => 'local',
                    'base' => $incomingVariant,
                    'local' => $localVariant,
                    'incoming' => $incomingVariant,
                    'defaultChoice' => 'incoming',
                ]);
                $choiceCount++;
                $position = $localChange['end'];
                $localIndex++;
                continue;
            }

            if ($incomingChange && (! $localChange || (! static::changesOverlap($incomingChange, $localChange) && static::changeBefore($incomingChange, $localChange)))) {
                static::appendContextChunk($chunks, $merged, $base, $position, $incomingChange['start']);
                $baseVariant = array_slice($base, $incomingChange['start'], $incomingChange['end'] - $incomingChange['start']);
                $incomingVariant = static::applyChanges($base, $incomingChange['start'], $incomingChange['end'], [$incomingChange]);
                static::appendChoiceChunk($chunks, $merged, [
                    'id' => 'chunk-' . $chunkId++,
                    'state' => 'incoming',
                    'base' => $baseVariant,
                    'local' => $baseVariant,
                    'incoming' => $incomingVariant,
                    'defaultChoice' => 'incoming',
                ]);
                $choiceCount++;
                $position = $incomingChange['end'];
                $incomingIndex++;
                continue;
            }

            $groupStart = min($localChange['start'], $incomingChange['start']);
            $groupEnd = max($localChange['end'], $incomingChange['end']);
            $localGroup = [$localChange];
            $incomingGroup = [$incomingChange];
            $localIndex++;
            $incomingIndex++;

            $expanded = true;
            while ($expanded) {
                $expanded = false;

                while ($localIndex < count($localChanges) && static::changeTouchesSpan($localChanges[$localIndex], $groupStart, $groupEnd)) {
                    $localGroup[] = $localChanges[$localIndex];
                    $groupEnd = max($groupEnd, $localChanges[$localIndex]['end']);
                    $localIndex++;
                    $expanded = true;
                }

                while ($incomingIndex < count($incomingChanges) && static::changeTouchesSpan($incomingChanges[$incomingIndex], $groupStart, $groupEnd)) {
                    $incomingGroup[] = $incomingChanges[$incomingIndex];
                    $groupEnd = max($groupEnd, $incomingChanges[$incomingIndex]['end']);
                    $incomingIndex++;
                    $expanded = true;
                }
            }

            static::appendContextChunk($chunks, $merged, $base, $position, $groupStart);

            $baseVariant = array_slice($base, $groupStart, $groupEnd - $groupStart);
            $localVariant = static::applyChanges($base, $groupStart, $groupEnd, $localGroup);
            $incomingVariant = static::applyChanges($base, $groupStart, $groupEnd, $incomingGroup);

            if ($localVariant === $incomingVariant) {
                $chunks[] = [
                    'type' => 'choice',
                    'id' => 'chunk-' . $chunkId++,
                    'state' => 'same',
                    'defaultChoice' => 'incoming',
                    'content' => base64_encode(static::joinLines($incomingVariant, "\n")),
                    'base' => base64_encode(static::joinLines($baseVariant, "\n")),
                    'local' => base64_encode(static::joinLines($localVariant, "\n")),
                    'incoming' => base64_encode(static::joinLines($incomingVariant, "\n")),
                ];
                $merged = array_merge($merged, $incomingVariant);
            } else {
                $state = 'conflict';
                if ($localVariant === $baseVariant) {
                    $state = 'incoming';
                } elseif ($incomingVariant === $baseVariant) {
                    $state = 'local';
                } else {
                    $conflictCount++;
                }
                static::appendChoiceChunk($chunks, $merged, [
                    'id' => 'chunk-' . $chunkId++,
                    'state' => $state,
                    'base' => $baseVariant,
                    'local' => $localVariant,
                    'incoming' => $incomingVariant,
                    'defaultChoice' => 'incoming',
                ]);
                $choiceCount++;
            }

            $position = $groupEnd;
        }

        static::appendContextChunk($chunks, $merged, $base, $position, count($base));

        return [
            'lineEnding' => base64_encode($lineEnding),
            'chunks' => $chunks,
            'mergedContent' => base64_encode(static::joinLines($merged, $lineEnding)),
            'hasChoices' => $choiceCount > 0,
            'hasConflicts' => $conflictCount > 0,
            'choiceCount' => $choiceCount,
            'conflictCount' => $conflictCount,
        ];
    }

    /**
     * Build a whole-file merge fallback when chunk detection is unavailable.
     *
     * @param string $baseContent
     * @param string $localContent
     * @param string $incomingContent
     *
     * @return array
     */
    public static function buildWholeFilePreview($baseContent, $localContent, $incomingContent)
    {
        $lineEnding = static::detectLineEnding($localContent, $incomingContent, $baseContent);
        $defaultChoice = 'incoming';

        return [
            'lineEnding' => base64_encode($lineEnding),
            'chunks' => [[
                'type' => 'choice',
                'id' => 'chunk-1',
                'state' => 'conflict',
                'defaultChoice' => $defaultChoice,
                'base' => base64_encode($baseContent),
                'local' => base64_encode($localContent),
                'incoming' => base64_encode($incomingContent),
            ]],
            'mergedContent' => base64_encode($defaultChoice === 'local' ? $localContent : $incomingContent),
            'hasChoices' => true,
            'hasConflicts' => true,
            'choiceCount' => 1,
            'conflictCount' => 1,
            'fallback' => true,
        ];
    }

    /**
     * Build a simple unified-style preview between two text blobs.
     *
     * @param string $fromContent
     * @param string $toContent
     * @param string $fromLabel
     * @param string $toLabel
     *
     * @return string
     */
    public static function buildDiff($fromContent, $toContent, $fromLabel = 'local', $toLabel = 'incoming')
    {
        $from = static::splitLines($fromContent);
        $to = static::splitLines($toContent);
        $ops = static::calculateOperations($from, $to);

        $lines = [
            '--- ' . $fromLabel . "\n",
            '+++ ' . $toLabel . "\n",
        ];

        foreach ($ops as $op) {
            switch ($op['type']) {
                case 'equal':
                    $lines[] = ' ' . $op['line'];
                    break;
                case 'delete':
                    $lines[] = '-' . $op['line'];
                    break;
                case 'insert':
                    $lines[] = '+' . $op['line'];
                    break;
            }
        }

        return implode('', $lines);
    }

    /**
     * @param array $chunks
     * @param array $merged
     * @param array $base
     * @param int $start
     * @param int $end
     *
     * @return void
     */
    private static function appendContextChunk(&$chunks, &$merged, $base, $start, $end)
    {
        if ($end <= $start) {
            return;
        }

        $lines = array_slice($base, $start, $end - $start);
        $chunks[] = [
            'type' => 'context',
            'content' => base64_encode(static::joinLines($lines, "\n")),
        ];
        $merged = array_merge($merged, $lines);
    }

    /**
     * @param array $chunks
     * @param array $merged
     * @param array $chunk
     *
     * @return void
     */
    private static function appendChoiceChunk(&$chunks, &$merged, $chunk)
    {
        $chunks[] = [
            'type' => 'choice',
            'id' => $chunk['id'],
            'state' => $chunk['state'],
            'defaultChoice' => $chunk['defaultChoice'],
            'base' => base64_encode(static::joinLines($chunk['base'], "\n")),
            'local' => base64_encode(static::joinLines($chunk['local'], "\n")),
            'incoming' => base64_encode(static::joinLines($chunk['incoming'], "\n")),
        ];
        $merged = array_merge($merged, $chunk[$chunk['defaultChoice']]);
    }

    /**
     * @param array $base
     * @param array $target
     *
     * @return array
     */
    private static function buildChanges($base, $target)
    {
        $changes = static::buildChangesUsingDiff($base, $target);
        if ($changes !== null) {
            return $changes;
        }

        $ops = static::calculateOperations($base, $target);
        $changes = [];
        $baseIndex = 0;
        $targetIndex = 0;
        $current = null;

        foreach ($ops as $op) {
            if ($op['type'] === 'equal') {
                if ($current) {
                    $current['end'] = $baseIndex;
                    $changes[] = $current;
                    $current = null;
                }
                $baseIndex++;
                $targetIndex++;
                continue;
            }

            if (! $current) {
                $current = [
                    'start' => $baseIndex,
                    'end' => $baseIndex,
                    'replacement' => [],
                ];
            }

            if ($op['type'] === 'delete') {
                $baseIndex++;
            } else {
                $current['replacement'][] = $target[$targetIndex];
                $targetIndex++;
            }
        }

        if ($current) {
            $current['end'] = $baseIndex;
            $changes[] = $current;
        }

        return $changes;
    }

    /**
     * @param array $base
     * @param int $start
     * @param int $end
     * @param array $changes
     *
     * @return array
     */
    private static function applyChanges($base, $start, $end, $changes)
    {
        $result = [];
        $cursor = $start;

        foreach ($changes as $change) {
            if ($change['start'] > $cursor) {
                $result = array_merge($result, array_slice($base, $cursor, $change['start'] - $cursor));
            }
            $result = array_merge($result, $change['replacement']);
            $cursor = $change['end'];
        }

        if ($cursor < $end) {
            $result = array_merge($result, array_slice($base, $cursor, $end - $cursor));
        }

        return $result;
    }

    /**
     * @param array $from
     * @param array $to
     *
     * @return array
     */
    private static function calculateOperations($from, $to)
    {
        $fromCount = count($from);
        $toCount = count($to);

        if ($fromCount === 0 && $toCount === 0) {
            return [];
        }

        if ($fromCount > static::MATRIX_FALLBACK_LIMIT || $toCount > static::MATRIX_FALLBACK_LIMIT) {
            return static::buildOperationsLinearly($from, $to);
        }

        $matrix = [];
        for ($fromIndex = 0; $fromIndex <= $fromCount; $fromIndex++) {
            $matrix[$fromIndex] = array_fill(0, $toCount + 1, 0);
        }

        $operations = [];
        for ($fromIndex = $fromCount - 1; $fromIndex >= 0; $fromIndex--) {
            for ($toIndex = $toCount - 1; $toIndex >= 0; $toIndex--) {
                if ($from[$fromIndex] === $to[$toIndex]) {
                    $matrix[$fromIndex][$toIndex] = $matrix[$fromIndex + 1][$toIndex + 1] + 1;
                } else {
                    $matrix[$fromIndex][$toIndex] = max($matrix[$fromIndex + 1][$toIndex], $matrix[$fromIndex][$toIndex + 1]);
                }
            }
        }

        $fromIndex = 0;
        $toIndex = 0;
        while ($fromIndex < $fromCount && $toIndex < $toCount) {
            if ($from[$fromIndex] === $to[$toIndex]) {
                $operations[] = [
                    'type' => 'equal',
                    'line' => $from[$fromIndex],
                ];
                $fromIndex++;
                $toIndex++;
            } elseif ($matrix[$fromIndex + 1][$toIndex] >= $matrix[$fromIndex][$toIndex + 1]) {
                $operations[] = [
                    'type' => 'delete',
                    'line' => $from[$fromIndex],
                ];
                $fromIndex++;
            } else {
                $operations[] = [
                    'type' => 'insert',
                    'line' => $to[$toIndex],
                ];
                $toIndex++;
            }
        }

        while ($fromIndex < $fromCount) {
            $operations[] = [
                'type' => 'delete',
                'line' => $from[$fromIndex],
            ];
            $fromIndex++;
        }

        while ($toIndex < $toCount) {
            $operations[] = [
                'type' => 'insert',
                'line' => $to[$toIndex],
            ];
            $toIndex++;
        }

        return $operations;
    }

    /**
     * Try to calculate changes using external diff utility.
     *
     * @param array $base
     * @param array $target
     *
     * @return array|null
     */
    private static function buildChangesUsingDiff($base, $target)
    {
        $diff = static::runDiff(
            static::joinLines($base, "\n"),
            static::joinLines($target, "\n"),
            0,
            'base',
            'target'
        );

        if ($diff === null) {
            return null;
        }

        if ($diff === '') {
            return [];
        }

        if (strpos($diff, '@@ ') === false) {
            return null;
        }

        $changes = [];
        $lines = preg_split("/\r\n|\n|\r/", $diff);
        foreach ($lines as $line) {
            if (!preg_match('/^@@ -([0-9]+)(?:,([0-9]+))? \+([0-9]+)(?:,([0-9]+))? @@/', $line, $matches)) {
                continue;
            }

            $baseStart = (int)$matches[1];
            $baseCount = isset($matches[2]) && $matches[2] !== '' ? (int)$matches[2] : 1;
            $targetStart = (int)$matches[3];
            $targetCount = isset($matches[4]) && $matches[4] !== '' ? (int)$matches[4] : 1;

            $baseIndex = $baseCount === 0 ? $baseStart : max(0, $baseStart - 1);
            $targetIndex = $targetCount === 0 ? $targetStart : max(0, $targetStart - 1);

            $changes[] = [
                'start' => $baseIndex,
                'end' => $baseIndex + $baseCount,
                'replacement' => array_slice($target, $targetIndex, $targetCount),
            ];
        }

        return $changes;
    }

    /**
     * Build operations in linear time for large files.
     *
     * This fallback treats the remaining tail as one replacement block.
     *
     * @param array $from
     * @param array $to
     *
     * @return array
     */
    private static function buildOperationsLinearly($from, $to)
    {
        $fromCount = count($from);
        $toCount = count($to);
        $prefix = 0;
        while ($prefix < $fromCount && $prefix < $toCount && $from[$prefix] === $to[$prefix]) {
            $prefix++;
        }

        $suffix = 0;
        while (
            $suffix < ($fromCount - $prefix) &&
            $suffix < ($toCount - $prefix) &&
            $from[$fromCount - $suffix - 1] === $to[$toCount - $suffix - 1]
        ) {
            $suffix++;
        }

        $operations = [];
        for ($index = 0; $index < $prefix; $index++) {
            $operations[] = [
                'type' => 'equal',
                'line' => $from[$index],
            ];
        }

        for ($index = $prefix; $index < $fromCount - $suffix; $index++) {
            $operations[] = [
                'type' => 'delete',
                'line' => $from[$index],
            ];
        }

        for ($index = $prefix; $index < $toCount - $suffix; $index++) {
            $operations[] = [
                'type' => 'insert',
                'line' => $to[$index],
            ];
        }

        for ($index = $fromCount - $suffix; $index < $fromCount; $index++) {
            $operations[] = [
                'type' => 'equal',
                'line' => $from[$index],
            ];
        }

        return $operations;
    }

    /**
     * @param string $fromContent
     * @param string $toContent
     * @param int $contextLines
     * @param string $fromLabel
     * @param string $toLabel
     *
     * @return string|null
     */
    private static function runDiff($fromContent, $toContent, $contextLines, $fromLabel, $toLabel)
    {
        if (!function_exists('shell_exec')) {
            return null;
        }

        $tempDir = static::getTempDirectory();
        $tmpFrom = tempnam($tempDir, 'cud');
        $tmpTo = tempnam($tempDir, 'cud');
        if (!$tmpFrom || !$tmpTo) {
            return null;
        }

        try {
            file_put_contents($tmpFrom, $fromContent);
            file_put_contents($tmpTo, $toContent);

            $command = sprintf(
                'diff -U %d --label %s --label %s %s %s 2>&1',
                (int)$contextLines,
                escapeshellarg($fromLabel),
                escapeshellarg($toLabel),
                escapeshellarg($tmpFrom),
                escapeshellarg($tmpTo)
            );

            $output = shell_exec($command);
            if ($output === null) {
                return null;
            }

            return preg_replace('/^(--- .*\R\+\+\+ .*\R)/', '', $output, 1);
        } finally {
            @unlink($tmpFrom);
            @unlink($tmpTo);
        }
    }

    /**
     * @return string
     */
    private static function getTempDirectory()
    {
        if (defined('_PS_CACHE_DIR_') && _PS_CACHE_DIR_ && is_dir(_PS_CACHE_DIR_)) {
            return _PS_CACHE_DIR_;
        }
        return sys_get_temp_dir();
    }

    /**
     * @param array $left
     * @param array $right
     *
     * @return bool
     */
    private static function changeBefore($left, $right)
    {
        if ($left['start'] < $right['start']) {
            return true;
        }

        if ($left['start'] > $right['start']) {
            return false;
        }

        return $left['end'] <= $right['end'];
    }

    /**
     * @param array $left
     * @param array $right
     *
     * @return bool
     */
    private static function changesOverlap($left, $right)
    {
        $leftInsert = $left['start'] === $left['end'];
        $rightInsert = $right['start'] === $right['end'];

        if ($leftInsert && $rightInsert) {
            return $left['start'] === $right['start'];
        }

        if ($leftInsert) {
            return $left['start'] > $right['start'] && $left['start'] < $right['end'];
        }

        if ($rightInsert) {
            return $right['start'] > $left['start'] && $right['start'] < $left['end'];
        }

        return $left['start'] < $right['end'] && $right['start'] < $left['end'];
    }

    /**
     * @param array $change
     * @param int $start
     * @param int $end
     *
     * @return bool
     */
    private static function changeTouchesSpan($change, $start, $end)
    {
        if ($change['start'] === $change['end']) {
            if ($start === $end) {
                return $change['start'] === $start;
            }
            return $change['start'] >= $start && $change['start'] <= $end;
        }

        return $change['start'] < $end && $change['end'] > $start;
    }

    /**
     * @param string $content
     *
     * @return array
     */
    private static function splitLines($content)
    {
        if ($content === '' || $content === null) {
            return [];
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $content);
        $lines = preg_split('/(?<=\n)/', $normalized);
        if ($lines && end($lines) === '') {
            array_pop($lines);
        }
        return $lines;
    }

    /**
     * @param array $lines
     * @param string $lineEnding
     *
     * @return string
     */
    private static function joinLines($lines, $lineEnding)
    {
        $content = implode('', $lines);
        if ($lineEnding !== "\n") {
            $content = str_replace("\n", $lineEnding, $content);
        }
        return $content;
    }

    /**
     * @param string $localContent
     * @param string $incomingContent
     * @param string $baseContent
     *
     * @return string
     */
    private static function detectLineEnding($localContent, $incomingContent, $baseContent)
    {
        foreach ([$localContent, $incomingContent, $baseContent] as $content) {
            if (strpos($content, "\r\n") !== false) {
                return "\r\n";
            }
            if (strpos($content, "\n") !== false) {
                return "\n";
            }
        }
        return "\n";
    }
}
