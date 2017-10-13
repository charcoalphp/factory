<?php

namespace Charcoal\Factory;

// From 'charcoal-factory'
use Charcoal\Factory\Exception\InvalidArgumentException;
use Charcoal\Factory\Exception\InvalidClassException;
use Charcoal\Factory\FactoryInterface;
use Charcoal\Factory\GenericResolver;

/**
 * Full implementation, as Abstract class, of the FactoryInterface.
 *
 * ## Class dependencies:
 *
 * | Name               | Type       | Description                            |
 * | ------------------ | ---------- | -------------------------------------- |
 * | `base_class`       | _string_   | Optional. A base class (or interface) to ensure a type of object.
 * | `default_class`    | _string_   | Optional. A default class, as fallback when the requested object is not resolvable.
 * | `arguments`        | _array_    | Optional. Constructor arguments that will be passed along to created instances.
 * | `callback`         | _Callable_ | Optional. A callback function that will be called upon object creation.
 * | `resolver`         | _Callable_ | Optional. A class resolver. If none is provided, a default will be used.
 * | `resolver_options` | _array_    | Optional. Resolver options (prefix, suffix, capitals and replacements). This is ignored / unused if `resolver` is provided.
 *
 */
abstract class AbstractFactory implements FactoryInterface
{
    /**
     * The base class name or interface.
     *
     * If a base class is defined, the requested class must be of this class,
     * have this class as one of its parents, or implement it.
     *
     * @var string|null
     */
    private $baseClass;

    /**
     * The fallback class name.
     *
     * If a default class is defined, the fallback is instantiated instead of throwing an error.
     *
     * @var string|null
     */
    private $defaultClass;

    /**
     * The default parameters to be passed to the class constructor.
     *
     * Note: These parameters can be overriden with the `create()` and `get()` methods.
     *
     * @var array|null
     */
    private $arguments;

    /**
     * The default routine to call for every new class instance.
     *
     * Note: Used with the `create()` method only.
     *
     * @var callable|null
     */
    private $callback;

    /**
     * The identifier to class name resolver.
     *
     * @var callable
     */
    private $resolver;

    /**
     * A pool of resolved identifiers to class names (`[ $type => $className ]`).
     *
     * @var string[]
     */
    protected $resolved = [];

    /**
     * A pool of resolved identifiers to class instances (`[ $type => $instance ]`).
     *
     * Note: Used with the `get()` method only.
     *
     * @var object[]
     */
    private $instances = [];

    /**
     * A map of aliases; identifiers to class names (`[ $type => $className ]`).
     *
     * @var string[]
     */
    private $map = [];

    /**
     * Create a new factory.
     *
     * @param array $data Constructor dependencies.
     */
    public function __construct(array $data = null)
    {
        if (isset($data['base_class'])) {
            $this->setBaseClass($data['base_class']);
        }

        if (isset($data['default_class'])) {
            $this->setDefaultClass($data['default_class']);
        }

        if (isset($data['arguments'])) {
            $this->setArguments($data['arguments']);
        }

        if (isset($data['callback'])) {
            $this->setCallback($data['callback']);
        }

        if (!isset($data['resolver'])) {
            $opts = isset($data['resolver_options']) ? $data['resolver_options'] : null;
            $data['resolver'] = new GenericResolver($opts);
        }

        $this->setResolver($data['resolver']);

        if (isset($data['map'])) {
            $this->setMap($data['map']);
        }
    }

    /**
     * Create a new instance of a class, by type.
     *
     * Unlike `get()`, this method *always* return a new instance of the requested class.
     *
     * ## Object callback
     *
     * It is possible to pass a callback method that will be executed upon object instanciation.
     * The callable should have a signature: `function($obj);` where $obj is the newly created object.
     *
     * @param  string   $type The type (class ident).
     * @param  array    $args Optional. Constructor arguments (will override the arguments set on the class from constructor).
     * @param  callable $cb   Optional. Object callback, called at creation. Will run in addition to the default callback, if any.
     * @throws Exception If the base class is set and  the resulting instance is not of the base class.
     * @throws InvalidArgumentException If type argument is not a string or is not an available type.
     * @return mixed The instance / object
     *
     *
     *
     * @param  string   $type     Object type or class name to create.
     * @param  array    $args     Optional. Parameters to be passed to the class constructor.
     *     If specified, overrides the {@see self::$arguments} initially assigned to the factory.
     * @param  callable $callback Optional. Callback that will be called and passed the new class instance.
     *     If specified, overrides the {@see self::$callback} initially assigned to the factory.
     * @throws InvalidArgumentException If $type is not a string or is not an available type.
     * @return mixed A new instance of $type.
     */
    final public function create($type, array $args = null, callable $cb = null)
    {
        if (!is_string($type)) {
            throw new InvalidArgumentException(sprintf(
                '[%1$s] Type must be a string, received "%2$s"',
                get_called_class(),
                is_object($type) ? get_class($type) : gettype($type)
            ));
        }

        if (!isset($args)) {
            $args = $this->arguments();
        }

        $pool = get_called_class();
        if (isset($this->resolved[$pool][$type])) {
            $className = $this->resolved[$pool][$type];
        } else {
            if ($this->isResolvable($type) === false) {
                $defaultClass = $this->defaultClass();
                if ($defaultClass !== '') {
                    $obj = $this->createClass($defaultClass, $args);
                    $this->runCallbacks($obj, $cb);
                    return $obj;
                } else {
                    throw new InvalidArgumentException(sprintf(
                        '[%1$s] Type "%2$s" is not a valid type. (Using default class "%3$s")',
                        get_called_class(),
                        $type,
                        $defaultClass
                    ));
                }
            }

            // Create the object from the type's class name.
            $className = $this->resolve($type);
            $this->resolved[$pool][$type] = $className;
        }

        $obj = $this->createClass($className, $args);

        // Ensure base class is respected, if set.
        $baseClass = $this->baseClass();
        if ($baseClass !== '' && !($obj instanceof $baseClass)) {
            throw new Exception(sprintf(
                '[%1$s] Class "%2$s" must be an instance of "%3$s"',
                get_called_class(),
                $className,
                $baseClass
            ));
        }

        $this->runCallbacks($obj, $cb);

        return $obj;
    }

    /**
     * Run the callback(s) on the object, if applicable.
     *
     * @param mixed    $obj            The object to pass to callback(s).
     * @param callable $customCallback An optional additional custom callback.
     * @return void
     */
    private function runCallbacks(&$obj, callable $customCallback = null)
    {
        $factoryCallback = $this->callback();
        if (isset($factoryCallback)) {
            $factoryCallback($obj);
        }
        if (isset($customCallback)) {
            $customCallback($obj);
        }
    }

    /**
     * Create a class instance with given arguments.
     *
     * How the constructor arguments are passed depends on its type:
     *
     * - if null, no arguments are passed at all.
     * - if it's not an array, it's passed as a single argument.
     * - if it's an associative array, it's passed as a sing argument.
     * - if it's a sequential (numeric keys) array, it's
     *
     * @param  string $className The fully-qualified class name to instantiate.
     * @param  mixed  $args      Optional. Parameters to be passed to the class constructor.
     * @return object A new instance of $className.
     */
    protected function createClass($className, $args = null)
    {
        if ($args === null) {
            return new $className;
        }
        if (!is_array($args)) {
            return new $className($args);
        }
        if (count(array_filter(array_keys($args), 'is_string')) > 0) {
            return new $className($args);
        } else {
            // Use argument unpacking (`return new $className(...$args);`) when minimum PHP requirement is bumped to 5.6.
            $reflection = new \ReflectionClass($className);
            return $reflection->newInstanceArgs($args);
        }
    }

    /**
     * Get (load or create) an instance of a class, by type.
     *
     * Unlike `create()` (which always call a `new` instance), this function first tries to load / reuse
     * an already created object of this type, from memory.
     *
     * @param string $type The type (class ident).
     * @param array  $args The constructor arguments (optional).
     * @throws InvalidArgumentException If type argument is not a string.
     * @return mixed The instance / object
     */
    final public function get($type, array $args = null)
    {
        if (!is_string($type)) {
            throw new InvalidArgumentException(
                'Type must be a string.'
            );
        }
        if (!isset($this->instances[$type]) || $this->instances[$type] === null) {
            $this->instances[$type] = $this->create($type, $args);
        }
        return $this->instances[$type];
    }

    /**
     * @param callable $resolver The class resolver instance to use.
     * @return FactoryInterface Chainable
     */
    private function setResolver(callable $resolver)
    {
        $this->resolver = $resolver;
        return $this;
    }

    /**
     * @return callable
     */
    protected function resolver()
    {
        return $this->resolver;
    }

    /**
     * Add multiple types, in a an array of `type` => `className`.
     *
     * @param string[] $map The map (key=>className) to use.
     * @return FactoryInterface Chainable
     */
    private function setMap(array $map)
    {
        // Resets (overwrites) map.
        $this->map = [];
        foreach ($map as $type => $className) {
            $this->addClassToMap($type, $className);
        }
        return $this;
    }

    /**
     * Get the map of all types in `[$type => $class]` format.
     *
     * @return string[]
     */
    protected function map()
    {
        return $this->map;
    }

    /**
     * Add a class name to the available types _map_.
     *
     * @param string $type      The type (class ident).
     * @param string $className The FQN of the class.
     * @throws InvalidArgumentException If the $type parameter is not a striing or the $className class does not exist.
     * @return FactoryInterface Chainable
     */
    protected function addClassToMap($type, $className)
    {
        if (!is_string($type)) {
            throw new InvalidArgumentException(
                'Type (class key) must be a string'
            );
        }

        $this->map[$type] = $className;
        return $this;
    }

    /**
     * Attach a base class or interface to the factory.
     *
     * If a base class is defined, the requested class(es) must be of this class,
     * have this class as one of its parents, or implement it.
     *
     * @param  string $type Object type, class name, or interface to set as the base class.
     *
     *
     *
     * @param  string $type The FQN of the class, or "type" of object, to set as base class.
     * @throws InvalidArgumentException If the class is not a string or is not an existing class / interface.
     * @return FactoryInterface Chainable
     */
    public function setBaseClass($type)
    {
        $this->assertValidClassName($type);

        $classExists = (class_exists($type) || interface_exists($type));
        if ($classExists) {
            $className = $type;
        } else {
            $className   = $this->resolve($type);
            $classExists = (class_exists($className) || interface_exists($className));
            if (!$classExists) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid base class: Class or interface "%s" not found',
                    $className
                ));
            }
        }

        $this->baseClass = $className;

        return $this;
    }

    /**
     * Retrieve the base class name or interface, if any.
     *
     * @return string|null The FQN of the base class, NULL otherwise.
     */
    public function baseClass()
    {
        return $this->baseClass;
    }

    /**
     * If a default class is set, then calling `get()` or `create()` an invalid type
     * should return an object of this class instead of throwing an error.
     *
     * @param  string $type The FQN of the class (or its snake-case variant).
     * @throws InvalidArgumentException If the default class is invalid or not found.
     * @return FactoryInterface Chainable
     */
    public function setDefaultClass($type)
    {
        $this->assertValidClassName($type);

        if (class_exists($type)) {
            $className = $type;
        } else {
            $className = $this->resolve($type);
            if (!class_exists($className)) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid default class: Class "%s" not found',
                    $className
                ));
            }
        }

        $this->defaultClass = $className;

        return $this;
    }

    /**
     * @return string The FQN of the default class
     */
    public function defaultClass()
    {
        return $this->defaultClass;
    }

    /**
     * @param array $arguments The constructor arguments to be passed to the created object's initialization.
     * @return FactoryInterface Chainable
     */
    public function setArguments(array $arguments)
    {
        $this->arguments = $arguments;
        return $this;
    }

    /**
     * @return array
     */
    public function arguments()
    {
        return $this->arguments;
    }

    /**
     * @param callable $callback The object callback.
     * @return FactoryInterface Chainable
     */
    public function setCallback(callable $callback)
    {
        $this->callback = $callback;
        return $this;
    }

    /**
     * @return callable|null
     */
    public function callback()
    {
        return $this->callback;
    }

    /**
     * The Generic factory resolves the class name from an exact FQN.
     *
     * @param  string $type The class (in its snake-case variant) to resolve.
     * @return string The resolved class name (FQN).
     */
    public function resolve($type)
    {
        $this->assertValidObjectType($type);

        $map = $this->map();
        if (isset($map[$type])) {
            $type = $map[$type];
        }

        if (class_exists($type)) {
            return $type;
        }

        $resolver = $this->resolver();
        $resolved = $resolver($type);
        return $resolved;
    }

    /**
     * Wether a `type` is resolvable. The Generic Factory simply checks if the _FQN_ `type` class exists.
     *
     * @param  string $type The class (in its snake-case variant) to resolve.
     * @return boolean
     */
    public function isResolvable($type)
    {
        $this->assertValidObjectType($type);

        $map = $this->map();
        if (isset($map[$type])) {
            $type = $map[$type];
        }

        if (class_exists($type)) {
            return true;
        }

        $resolver = $this->resolver();
        $resolved = $resolver($type);
        if (class_exists($resolved)) {
            return true;
        }

        return false;
    }

    /**
     * Asserts that the object type is valid, throws an Exception if not.
     *
     * @param  string $type The name of the class (and namespace) to test.
     *    Either as a FQN or as snake-case.
     * @throws LogicException If the class is invalid.
     */
    protected function assertValidObjectType($type)
    {
        if (!is_string($type) || empty($type)) {
            throw new LogicException(sprintf(
                'Object type must be a non-empty string, received "%s"',
                is_object($type) ? get_class($type) : gettype($type)
            ));
        }
    }

    /**
     * Asserts that the class name is valid, throws an Exception if not.
     *
     * @param  string $type The name of the class (and namespace) to test.
     *    Either as a FQN or as snake-case.
     * @throws LogicException If the class is invalid.
     */
    protected function assertValidClassName($className)
    {
        if (!is_string($className) || empty($className)) {
            throw new LogicException(sprintf(
                'Class name must be a non-empty string, received "%s"',
                is_object($className) ? get_class($className) : gettype($className)
            ));
        }
    }
}
