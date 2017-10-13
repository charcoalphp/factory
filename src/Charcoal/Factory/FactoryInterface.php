<?php

namespace Charcoal\Factory;

use Charcoal\Factory\Exception\ExceptionInterface;

/**
 * Factories instanciate (create) objects.
 */
interface FactoryInterface
{
    /**
     * Create a new instance of a class, by object type.
     *
     * @param  string   $type     Object type or class name to create.
     * @param  array    $args     Optional. Parameters to be passed to the class constructor.
     * @param  callable $callback Optional. Callback that will be called and passed the new class instance.
     * @throws ExceptionInterface If the $type is invalid or can not be resolved into an existing class.
     * @return object A new instance of $type.
     */
    public function create($type, array $args = null, callable $callback = null);

    /**
     * Retrieve the base class name or interface, if any.
     *
     * @return string
     */
    public function baseClass();

    /**
     * Retrieve the fallback class name, if any.
     *
     * @return string
     */
    public function defaultClass();

    /**
     * Retrieve default parameters to be passed to the class constructor.
     *
     * @return array
     */
    public function arguments();

    /**
     * Retrieve default routine that will be called and passed the new class instance.
     *
     * @return callable|null
     */
    public function callback();
}
