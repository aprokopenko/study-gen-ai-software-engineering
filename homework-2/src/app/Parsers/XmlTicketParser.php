<?php

declare(strict_types=1);

namespace App\Parsers;

class XmlTicketParser implements TicketImportParserInterface
{
    public function parse(string $raw): iterable
    {
        if (trim($raw) === '') {
            throw new ParseException('XML input is empty');
        }

        // XXE protection: disable external entities and network access
        libxml_set_external_entity_loader(static fn() => null);
        $prev = libxml_use_internal_errors(true);

        $xml = simplexml_load_string($raw, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOENT);

        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if ($xml === false) {
            $msg = !empty($errors) ? trim($errors[0]->message) : 'Unknown XML error';
            throw new ParseException('Invalid XML: ' . $msg);
        }

        // Reject DTD declarations
        if (str_contains($raw, '<!DOCTYPE') || str_contains($raw, '<!ENTITY')) {
            throw new ParseException('XML with DTD/entity declarations is not allowed');
        }

        foreach ($xml->ticket as $ticket) {
            yield $this->nodeToArray($ticket);
        }
    }

    private function nodeToArray(\SimpleXMLElement $node): array
    {
        $row = [];

        foreach ($node->children() as $child) {
            $key = $child->getName();

            if ($child->count() > 0) {
                // Nested element (e.g. <metadata>)
                $row[$key] = $this->nodeToArray($child);
            } else {
                $value = (string) $child;
                $row[$key] = $value === '' ? null : $value;
            }
        }

        // tags: <tags><tag>foo</tag><tag>bar</tag></tags>
        if (isset($node->tags)) {
            $tags = [];
            foreach ($node->tags->tag ?? [] as $tag) {
                $tags[] = (string) $tag;
            }
            $row['tags'] = $tags ?: null;
        }

        return $row;
    }
}
