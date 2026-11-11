<?php

namespace App\Services;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer as SymfonySanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Cleans rich text entered in the admin (product descriptions, pages) so only
 * safe formatting tags and http(s)/mailto/tel links survive.
 */
class HtmlSanitizer
{
    private SymfonySanitizer $sanitizer;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig)
            ->allowElement('p')->allowElement('br')->allowElement('div')
            ->allowElement('strong')->allowElement('b')->allowElement('em')->allowElement('i')->allowElement('u')
            ->allowElement('h2')->allowElement('h3')->allowElement('h4')
            ->allowElement('ul')->allowElement('ol')->allowElement('li')
            ->allowElement('blockquote')->allowElement('hr')
            ->allowElement('a', ['href', 'title', 'target', 'rel'])
            ->allowLinkSchemes(['https', 'http', 'mailto', 'tel'])
            ->allowRelativeLinks()
            ->forceAttribute('a', 'rel', 'noopener noreferrer');

        $this->sanitizer = new SymfonySanitizer($config);
    }

    public function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        return trim($this->sanitizer->sanitize($html));
    }
}
