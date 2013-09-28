<?php
/**
 * @author Alexander Reece
 * @copyright Alexander Reece (c) 2013
 * @license http://opensource.org/licenses/MIT
 */
namespace Reece45\Debug;

/**
 * Utility class to help analyze memory usage.
 *
 * @author Alexander Reece
 * @copyright Alexander Reece (c) 2013
 * @license http://opensource.org/licenses/MIT
 */
class MemoryAnalyzer
{
    /**
     * Analyze the children/properties of a reference
     * @param mixed $reference
     */
    static function printChildrenAnalysis(&$reference)
    {
        $instance = new self();
        $getProperty = $instance->getGetPropertyClosure();

        if (is_array($reference))
        {
            foreach($reference as $k => $v)
            {
                printf("[%s]: %s\n", $k, self::analyze($v));
            }
        }
        elseif (is_object($reference))
        {
            $objectReflector = new \ReflectionObject($reference);
            foreach($objectReflector->getProperties() as $property)
            {
                /* @var $property \ReflectionProperty */
                if (!$property->isStatic())
                {
                    $k = $property->getName();
                    $property->setAccessible(true);
                    $v = &$getProperty($reference, $k);

                    printf("[%s]: %s\n", $k, self::analyze($v));
                }
            }
        }
    }

    /**
     * Return a general number that may help understand how much memory
     * the given reference is using.
     * by a particular reference
     * @param type $reference
     * @return type
     */
    static function analyze(&$reference)
    {
        $return = 0;
        if (\is_scalar($reference))
        {
            $return += strlen($reference);
        }
        elseif (\is_resource($reference))
        {
            $return += \get_resource_type($reference);
        }
        elseif (\is_array($reference) || \is_object($reference))
        {
            $return += self::analyzeTree($reference);
        }
        return $return;
    }

    /**
     * Enumerate the children of an array or object recursively.
     * @param type $reference
     * @return type
     */
    static function analyzeTree(&$reference)
    {
        $return = 0;

        $stack = array();
        $stack[] = &$reference;

        $visitedObjects = array();
        $visitedArrays = array();

        $magicKey = uniqid('__DebugMemory:');

        // may not be able to set $this from a static method, see https://bugs.php.net/bug.php?id=64761
        $instance = new self();
        $getProperty = $instance->getGetPropertyClosure();

        while (isset($stack[0]))
        {
            $item = &$stack[0];
            if(is_array($item))
            {
                $item[$magicKey] = true;
                $visitedArrays[] = &$item;

                foreach($item as $k => &$v)
                {
                    if($k == $magicKey)
                    {
                        continue;
                    }

                    if (\is_object($v))
                    {
                        $objectHash = \spl_object_hash($v);
                        if(isset($visitedObjects[$objectHash]))
                        {
                            continue;
                        }
                        $visitedObjects[$objectHash] = true;
                        $stack[] = &$item[$k];
                    }

                    elseif (\is_array($v))
                    {
                        if(isset($v[$magicKey]))
                        {
                            continue;
                        }
                        $stack[] = &$item[$k];
                    }

                    else
                    {
                        $return += self::analyze($v);
                    }
                }
            }
            else
            {
                $objectReflector = new \ReflectionObject($item);
                foreach($objectReflector->getProperties() as $p)
                {
                    /* @var $p \ReflectionProperty */
                    if (!$p->isStatic())
                    {
                        $k = $p->getName();
                        $v = &$getProperty($item, $k);

                        if (\is_object($v))
                        {
                            $objectHash = \spl_object_hash($v);
                            if(isset($visitedObjects[$objectHash]))
                            {
                                continue;
                            }
                            $visitedObjects[$objectHash] = true;
                            $stack[] = &$v;
                        }

                        elseif (\is_array($v))
                        {
                            if(isset($v[$magicKey]))
                            {
                                continue;
                            }
                            $stack[] = &$v;
                        }

                        else
                        {
                            $return += self::analyze($v);
                        }
                    }
                }
            }
            \array_shift($stack);
        }

        foreach($visitedArrays as &$array)
        {
            unset($array[$magicKey]);
        }
        return $return;
    }

    /**
     * Returns a closer that may be used to retrieve a property by reference
     * @return \Closureb
     */
    function getGetPropertyClosure()
    {
        return function & ($object, $property)
          {
              $getProperty = function & () use ($property)
                {
                    return $this->$property;
                };

              $value = &$getProperty->bind($getProperty, $object, $object)->__invoke();;
              return $value;
          };
    }
}
