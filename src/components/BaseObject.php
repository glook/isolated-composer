<?php
/**
 * Created by: Andrey Polyakov (andrey@polyakov.im)
 */

namespace Glook\IsolatedComposer\components;

use Exception;

/**
 * Class BaseObject
 * @package Glook\IsolatedComposer\components
 */
abstract class BaseObject
{
	public function __construct(array $config = [])
	{
		if (!empty($config)) {
			foreach ($config as $name => $value) {
				$this->$name = $value;
			}

		}
		$this->init();
	}

	/**
	 * Initializes the object.
	 * This method is invoked at the end of the constructor after the object is initialized with the
	 * given configuration.
	 */
	public function init(): void
	{
	}

	/**
	 * Returns the value of an object property.
	 * Do not call this method directly as it is a PHP magic method that
	 * will be implicitly called when executing `$value = $object->property;`.
	 * @param string $name the property name
	 * @return mixed the property value
	 * @throws Exception if the property is not defined
	 * @throws Exception if the property is write-only
	 * @see __set()
	 */
	public function __get(string $name)
	{
		$getter = 'get' . $name;
		if (method_exists($this, $getter)) {
			return $this->$getter();
		}

		if (method_exists($this, 'set' . $name)) {
			throw new Exception('Getting write-only property: ' . get_class($this) . '::' . $name);
		}

		throw new Exception('Getting unknown property: ' . get_class($this) . '::' . $name);
	}

	/**
	 * Sets value of an object property.
	 * Do not call this method directly as it is a PHP magic method that
	 * will be implicitly called when executing `$object->property = $value;`.
	 * @param string $name the property name or the event name
	 * @param mixed $value the property value
	 * @throws Exception if the property is not defined
	 * @see __get()
	 */
	public function __set(string $name, $value)
	{
		$setter = 'set' . $name;
		if (method_exists($this, $setter)) {
			$this->$setter($value);
		} elseif (method_exists($this, 'get' . $name)) {
			throw new Exception('Setting read-only property: ' . get_class($this) . '::' . $name);
		} else {
			throw new Exception('Setting unknown property: ' . get_class($this) . '::' . $name);
		}
	}

	/**
	 * Checks if a property is set, i.e. defined and not null.
	 * Do not call this method directly as it is a PHP magic method that
	 * will be implicitly called when executing `isset($object->property)`.
	 * Note that if the property is not defined, false will be returned.
	 * @param string $name the property name or the event name
	 * @return bool whether the named property is set (not null).
	 * @see https://secure.php.net/manual/en/function.isset.php
	 */
	public function __isset(string $name): bool
	{
		$getter = 'get' . $name;
		if (method_exists($this, $getter)) {
			return $this->$getter() !== null;
		}

		return false;
	}

	/**
	 * Sets an object property to null.
	 * Do not call this method directly as it is a PHP magic method that
	 * will be implicitly called when executing `unset($object->property)`.
	 * Note that if the property is not defined, this method will do nothing.
	 * If the property is read-only, it will throw an exception.
	 * @param string $name the property name
	 * @throws Exception if the property is read only.
	 * @see https://secure.php.net/manual/en/function.unset.php
	 */
	public function __unset(string $name)
	{
		$setter = 'set' . $name;
		if (method_exists($this, $setter)) {
			$this->$setter(null);
		} elseif (method_exists($this, 'get' . $name)) {
			throw new Exception('Unsetting read-only property: ' . get_class($this) . '::' . $name);
		}
	}

	/**
	 * Calls the named method which is not a class method.
	 * Do not call this method directly as it is a PHP magic method that
	 * will be implicitly called when an unknown method is being invoked.
	 * @param string $name the method name
	 * @param array $params method parameters
	 * @return mixed the method return value
	 * @throws Exception when calling unknown method
	 */
	public function __call(string $name, array $params)
	{
		throw new Exception('Calling unknown method: ' . get_class($this) . "::$name()");
	}

}
