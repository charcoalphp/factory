<?php

namespace Charcoal\Factory\Exception;

use ReflectionClass;

/**
 * Exception thrown when the Factory can not process a class or object.
 */
class InvalidClassException extends \RuntimeException implements ExceptionInterface
{
    /**
     * Create a new InvalidArgumentException from an unresolved object type.
     *
     * @param  string $type The object type.
     * @return self A new Exception.
     */
    public static function fromUnresolvedType($type)
    {
        return new self(sprintf(
            'Object type "%s" can not be resolved',
            $type
        ));
    }

    /**
     * Create new InvalidArgumentException from a rejected class.
     *
     * @param  string $invalidClassName The invalid class name.
     * @param  string $baseClassName    The valid class, an inherited class, or interface.
     * @return self A new Exception.
     */
    public static function fromRejectedClass($invalidClassName, $validClassName)
    {
        if (empty($invalidClassName) || empty($validClassName)) {
            return new self('Class is invalid');
        }

        return new self(sprintf(
            'Class %s must be an instance of %s',
            $invalidClassName,
            $validClassName
        ));
    }
}
