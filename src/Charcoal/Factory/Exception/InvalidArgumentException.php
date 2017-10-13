<?php

namespace Charcoal\Factory\Exception;

use ReflectionClass;

/**
 * Exception thrown when the Factory can not process an argument.
 */
class InvalidArgumentException extends \InvalidArgumentException implements ExceptionInterface
{
    /**
     * Create a new InvalidArgumentException from an undefined or invalid object type.
     *
     * @param  string $type The object type.
     * @return self A new Exception.
     */
    public static function fromBadType($type)
    {
        if (empty($type)) {
            return new self('Object type is empty');
        }

        if (!is_string($type)) {
            return new self('Object type must be a string');
        }

        return new self(sprintf('Object type "%s" is invalid', $type));
    }

    /**
     * Create a new InvalidArgumentException from an undefined or invalid class.
     *
     * @param  string $className The interface, trait, or undefined class name.
     * @return self A new Exception.
     */
    public static function fromBadClass($className)
    {
        if (empty($className)) {
            return new self('Class is empty');
        }

        if (interface_exists($className)) {
            return new self(sprintf('Class %s is an interface', $className));
        }

        if (trait_exists($className)) {
            return new self(sprintf('Class %s is a trait', $className));
        }

        return new self(sprintf('Class %s does not exist', $className));
    }

    /**
     * Create new InvalidArgumentException from an abstract class.
     *
     * @param  ReflectionClass $reflectionClass The abstract class.
     * @return self A new Exception.
     */
    public static function fromAbstractClass(ReflectionClass $reflectionClass)
    {
        return new self(sprintf(
            'Class %s is abstract',
            $reflectionClass->getName()
        ));
    }
}
