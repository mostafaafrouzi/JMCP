<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service;

defined('_JEXEC') or die;

/**
 * Sanitizes HTML content produced by AI before saving to Joomla.
 */
class HtmlSanitizer
{
    /** @var string[] */
    private array $allowedTags = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'sub', 'sup',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li', 'a', 'img', 'blockquote', 'pre', 'code',
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
        'div', 'span', 'hr', 'figure', 'figcaption', 'video', 'source',
    ];

    /** @var string[] */
    private array $allowedAttributes = [
        'href', 'src', 'alt', 'title', 'class', 'id', 'target', 'rel',
        'width', 'height', 'colspan', 'rowspan',
    ];

    public function sanitize(string $html): string
    {
        if ($html === '') {
            return '';
        }

        // Strip PHP tags
        $html = preg_replace('/<\?(php)?.*?\?>/si', '', $html) ?? $html;

        // Use strip_tags with allowed list
        $allowed = '<' . implode('><', $this->allowedTags) . '>';
        $clean = strip_tags($html, $allowed);

        // Remove event handlers, javascript/data URLs, and inline styles
        $clean = preg_replace('/\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]*)/i', '', $clean) ?? $clean;
        $clean = preg_replace('/javascript\s*:/i', '', $clean) ?? $clean;
        $clean = preg_replace('/\s+style\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]*)/i', '', $clean) ?? $clean;
        $clean = preg_replace('/\s+(href|src)\s*=\s*("\s*data:[^"]*"|\'\s*data:[^\']*\'|data:[^\s>]*)/i', '', $clean) ?? $clean;

        return $clean;
    }

    public function textToHtml(string $text): string
    {
        if ($text === '') {
            return '';
        }

        if (str_contains($text, '<')) {
            return $this->sanitize($text);
        }

        $paragraphs = preg_split('/\n\s*\n/', $text) ?: [$text];
        $html = '';

        foreach ($paragraphs as $para) {
            $para = trim($para);
            if ($para === '') {
                continue;
            }
            $html .= '<p>' . nl2br(htmlspecialchars($para, ENT_QUOTES, 'UTF-8')) . '</p>';
        }

        return $html;
    }
}
