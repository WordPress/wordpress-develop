<?php

declare(strict_types=1);

namespace WordPress\DistributedEditing\Automerge;

use JsonSerializable;
use OutOfBoundsException;

final class TextValue implements JsonSerializable
{
    /** @var array<int,array{id:string,actor:string,seq:int,char:string,visible:bool,after?:?string,inserted?:bool}> */
    private array $elements;
    private ?string $compactText;
    private ?string $compactActor;
    private int $compactStartSeq;
    private ?int $cachedLength;
    private ?string $cachedString;

    /**
     * @param array<int,array{id:string,actor:string,seq:int,char:string,visible:bool,after?:?string,inserted?:bool}> $elements
     */
    public function __construct(
        array $elements = [],
        ?string $compactText = null,
        ?string $compactActor = null,
        int $compactStartSeq = 1,
        ?int $compactLength = null
    ) {
        $this->elements = array_map(
            static fn (array $element): array => $element + ['after' => null, 'inserted' => false],
            array_values($elements)
        );
        $this->compactText = $compactText;
        $this->compactActor = $compactText === null ? null : ($compactActor ?? '');
        $this->compactStartSeq = max(1, $compactStartSeq);
        $this->cachedLength = $compactText === null ? null : ($compactLength ?? self::countCharacters($compactText));
        $this->cachedString = $compactText;
    }

    public static function fromString(string $text, string $actor, int &$sequence): self
    {
        $length = self::countCharacters($text);
        $startSeq = $sequence + 1;
        $sequence += $length;

        return new self([], $text, $actor, $startSeq, $length);
    }

    public static function fromCompactString(string $text, string $actor, int $startSeq, ?int $elementCount = null): self
    {
        $length = $elementCount ?? self::countCharacters($text);

        return new self([], $text, $actor, $startSeq, $length);
    }

    public function copy(): self
    {
        if ($this->compactText !== null) {
            return new self([], $this->compactText, $this->compactActor, $this->compactStartSeq, $this->cachedLength);
        }

        return new self($this->elements);
    }

    public function length(): int
    {
        if ($this->cachedLength !== null) {
            return $this->cachedLength;
        }

        $length = 0;
        foreach ($this->elements as $element) {
            if ($element['visible']) {
                ++$length;
            }
        }

        $this->cachedLength = $length;

        return $length;
    }

    public function charAt(int $index): string
    {
        $visibleIndex = 0;
        foreach ($this->orderedElements() as $element) {
            if (! $element['visible']) {
                continue;
            }

            if ($visibleIndex === $index) {
                return $element['char'];
            }

            ++$visibleIndex;
        }

        throw new OutOfBoundsException('Text index is outside the visible text.');
    }

    public function splice(int $index, int $deleteCount, string $insert, string $actor, int &$sequence): void
    {
        if ($index < 0 || $deleteCount < 0 || $index > $this->length()) {
            throw new OutOfBoundsException('Text splice range is outside the visible text.');
        }

        $this->materializeElements();
        $this->invalidateCache();

        $after = $insert === '' ? null : $this->anchorForVisibleIndex($index);

        if ($deleteCount > 0) {
            $remaining = $deleteCount;
            $visibleIndex = 0;
            foreach ($this->orderedElements() as $element) {
                if (! $element['visible']) {
                    continue;
                }

                if ($visibleIndex >= $index && $remaining > 0) {
                    $this->elements[$element['offset']]['visible'] = false;
                    --$remaining;
                }

                ++$visibleIndex;
            }
        }

        if ($insert === '') {
            return;
        }

        $newElements = [];
        foreach ($this->splitCharacters($insert) as $char) {
            ++$sequence;
            $id = $sequence . '@' . $actor;
            $newElements[] = [
                'id' => $id,
                'actor' => $actor,
                'seq' => $sequence,
                'char' => $char,
                'visible' => true,
                'after' => $after,
                'inserted' => true,
            ];
            $after = $id;
        }

        array_push($this->elements, ...$newElements);
    }

    public function merge(self $other): self
    {
        /** @var array<string,array{id:string,actor:string,seq:int,char:string,visible:bool,first:int}> $byId */
        $byId = [];
        $position = 0;

        foreach ([$this->elements(), $other->elements()] as $source) {
            foreach ($source as $element) {
                if (! isset($byId[$element['id']])) {
                    $byId[$element['id']] = ($element + ['after' => null, 'inserted' => false]) + ['first' => $position];
                    ++$position;
                    continue;
                }

                $byId[$element['id']]['visible'] = $byId[$element['id']]['visible'] && $element['visible'];
            }
        }

        $merged = array_values($byId);
        usort(
            $merged,
            static function (array $left, array $right): int {
                $actor = $left['actor'] <=> $right['actor'];
                if ($actor !== 0) {
                    return $actor;
                }

                $sequence = $left['seq'] <=> $right['seq'];
                if ($sequence !== 0) {
                    return $sequence;
                }

                return $left['first'] <=> $right['first'];
            }
        );

        return new self(
            array_map(
                static fn (array $element): array => [
                    'id' => $element['id'],
                    'actor' => $element['actor'],
                    'seq' => $element['seq'],
                    'char' => $element['char'],
                    'visible' => $element['visible'],
                    'after' => $element['after'],
                    'inserted' => $element['inserted'],
                ],
                $merged
            )
        );
    }

    public function toString(): string
    {
        if ($this->cachedString !== null) {
            return $this->cachedString;
        }

        $text = '';
        foreach ($this->orderedElements() as $element) {
            if ($element['visible']) {
                $text .= $element['char'];
            }
        }

        $this->cachedString = $text;

        return $text;
    }

    public function jsonSerialize(): string
    {
        return $this->toString();
    }

    /**
     * @return array<int,array{id:string,actor:string,seq:int,char:string,visible:bool,after?:?string,inserted?:bool}>
     */
    public function elements(): array
    {
        $this->materializeElements();

        return $this->elements;
    }

    /**
     * @return array{type:string,elements?:array<int,array{id:string,actor:string,seq:int,char:string,visible:bool,after?:?string,inserted?:bool}>,value?:string,actor?:string,startSeq?:int,elementCount?:int}
     */
    public function encodedValue(): array
    {
        if ($this->compactText !== null) {
            return [
                'type' => 'text',
                'value' => $this->compactText,
                'actor' => $this->compactActor ?? '',
                'startSeq' => $this->compactStartSeq,
                'elementCount' => $this->length(),
            ];
        }

        return [
            'type' => 'text',
            'elements' => $this->elements(),
        ];
    }

    public function elementCount(): int
    {
        if ($this->compactText !== null) {
            return $this->length();
        }

        return count($this->elements);
    }

    /**
     * @param callable(string):int $measure
     * @return array{0:int,1:int}
     */
    public function elementBoundaryAfterMeasuredIndex(int $index, callable $measure): array
    {
        $index = max(0, $index);
        $offset = 0;
        $visibleIndex = 0;
        foreach ($this->orderedElements() as $element) {
            if (! $element['visible']) {
                continue;
            }

            if ($index <= $offset) {
                return [$visibleIndex, $offset];
            }

            $nextOffset = $offset + $measure($element['char']);
            if ($index <= $nextOffset) {
                return [$visibleIndex + 1, $nextOffset];
            }

            $offset = $nextOffset;
            ++$visibleIndex;
        }

        return [$visibleIndex, $offset];
    }

    private function anchorForVisibleIndex(int $index): ?string
    {
        if ($index === 0) {
            return null;
        }

        $visibleIndex = 0;
        foreach ($this->orderedElements() as $element) {
            if (! $element['visible']) {
                continue;
            }

            ++$visibleIndex;
            if ($visibleIndex === $index) {
                return $element['id'];
            }
        }

        return null;
    }

    /**
     * @return list<array{id:string,actor:string,seq:int,char:string,visible:bool,after:?string,inserted:bool,offset:int}>
     */
    private function orderedElements(): array
    {
        $this->materializeElements();

        $byParent = [];
        foreach ($this->elements as $offset => $element) {
            $element = ($element + ['after' => null, 'inserted' => false]) + ['offset' => $offset];
            $parent = $element['after'] ?? '';
            $byParent[$parent][] = $element;
        }

        foreach ($byParent as $parent => $children) {
            usort(
                $children,
                static function (array $left, array $right): int {
                    if ($left['inserted'] !== $right['inserted']) {
                        return $left['inserted'] ? -1 : 1;
                    }

                    $actor = $right['actor'] <=> $left['actor'];
                    if ($actor !== 0) {
                        return $actor;
                    }

                    return $right['seq'] <=> $left['seq'];
                }
            );
            $byParent[$parent] = $children;
        }

        $ordered = [];
        $visit = static function (?string $parent) use (&$visit, &$ordered, $byParent): void {
            $key = $parent ?? '';
            foreach ($byParent[$key] ?? [] as $child) {
                $ordered[] = $child;
                $visit($child['id']);
            }
        };
        $visit(null);

        return $ordered;
    }

    /**
     * @return list<string>
     */
    private function splitCharacters(string $text): array
    {
        return self::characters($text);
    }

    private function materializeElements(): void
    {
        if ($this->compactText === null) {
            return;
        }

        $text = $this->compactText;
        $actor = $this->compactActor ?? '';
        $sequence = $this->compactStartSeq - 1;
        $after = null;
        $elements = [];
        foreach (self::characters($text) as $char) {
            ++$sequence;
            $id = $sequence . '@' . $actor;
            $elements[] = [
                'id' => $id,
                'actor' => $actor,
                'seq' => $sequence,
                'char' => $char,
                'visible' => true,
                'after' => $after,
                'inserted' => false,
            ];
            $after = $id;
        }

        $this->elements = $elements;
        $this->compactText = null;
        $this->compactActor = null;
        $this->cachedString = $text;
        $this->cachedLength ??= count($elements);
    }

    private function invalidateCache(): void
    {
        $this->cachedLength = null;
        $this->cachedString = null;
    }

    private static function countCharacters(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        if (! preg_match('//u', $text)) {
            throw new \InvalidArgumentException('Text must be valid UTF-8.');
        }

        if (! preg_match('/[^\x00-\x7F]/', $text)) {
            return strlen($text);
        }

        return count(self::characters($text));
    }

    /**
     * @return list<string>
     */
    private static function characters(string $text): array
    {
        if (! preg_match_all('/\X/u', $text, $matches)) {
            if ($text === '') {
                return [];
            }

            throw new \InvalidArgumentException('Text must be valid UTF-8.');
        }

        return $matches[0];
    }
}
