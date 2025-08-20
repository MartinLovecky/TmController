<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins\Manager;

class EventContext
{
    public array $data = [];

    public function saveToContext(object $class): void
    {
        $className = get_class($class);

        $excluded = [
            self::class,
        ];

        if (in_array($className, $excluded, true)) {
            return;
        }

         $props = (new \ReflectionObject($class))->getProperties(
             \ReflectionProperty::IS_PRIVATE |
             \ReflectionProperty::IS_PROTECTED |
             \ReflectionProperty::IS_PUBLIC
         );

        $this->data[$className] = [];

        foreach ($props as $prop) {
            $name = $prop->getName();

            if (!$prop->isInitialized($class)) {
                continue;
            }

            $prop->setAccessible(true);
            $value = $prop->getValue($class);

            if (is_object($value)) {
                continue;
            }

            $this->data[get_class($class)][$name] = $value;
        }
    }
}
