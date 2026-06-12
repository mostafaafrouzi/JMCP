<?php

/**
 * @package     JMCP - Joomla MCP Server
 * @copyright   Copyright (C) 2026 JMCP Team. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service;

defined('_JEXEC') or die;

class SchemaValidator
{
    /**
     * Validate data against a simple JSON schema.
     *
     * @param array $data The input parameters.
     * @param array $schema The schema array.
     *
     * @return string|null Error message, or null if valid.
     */
    public function validate(array $data, array $schema): ?string
    {
        $errors = [];

        // Check required fields
        if (isset($schema['required']) && is_array($schema['required'])) {
            foreach ($schema['required'] as $requiredField) {
                if (!array_key_exists($requiredField, $data) || $data[$requiredField] === null || $data[$requiredField] === '') {
                    $errors[] = sprintf("Missing required parameter: '%s'", $requiredField);
                }
            }
        }

        // Validate properties
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $name => $prop) {
                if (!array_key_exists($name, $data) || $data[$name] === null) {
                    continue; // Skip optional missing fields
                }

                $value = $data[$name];

                // Validate type
                if (isset($prop['type'])) {
                    $type = $prop['type'];
                    $validType = true;

                    switch ($type) {
                        case 'string':
                            $validType = is_string($value);
                            break;
                        case 'integer':
                            $validType = is_int($value) || (is_string($value) && ctype_digit($value));
                            break;
                        case 'number':
                            $validType = is_numeric($value);
                            break;
                        case 'boolean':
                            $validType = is_bool($value) || $value === 0 || $value === 1 || $value === '0' || $value === '1' || $value === 'true' || $value === 'false';
                            break;
                        case 'array':
                            $validType = is_array($value) && (array_keys($value) === range(0, count($value) - 1) || empty($value));
                            break;
                        case 'object':
                            $validType = is_array($value);
                            break;
                    }

                    if (!$validType) {
                        $errors[] = sprintf("Parameter '%s' should be of type '%s'", $name, $type);
                    }
                }

                // Validate enum
                if (isset($prop['enum']) && is_array($prop['enum'])) {
                    if (!in_array($value, $prop['enum'], true)) {
                        $errors[] = sprintf("Parameter '%s' has invalid value. Allowed: [%s]", $name, implode(', ', $prop['enum']));
                    }
                }
            }
        }

        if (!empty($errors)) {
            return implode('; ', $errors);
        }

        return null;
    }
}
