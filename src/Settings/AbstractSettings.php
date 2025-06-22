<?php

namespace RWP\Settings;

class AbstractSettings
{
	use \RWP\Traits\FormatTrait;

	protected string $id;
	static protected string $type;
	static protected array $items = [];

	/**
	 * Store the instance of this Settings type
	 *
	 * @param string $id       The unique id of this Settings type
	 * @param mixed  $instance The instance of this Settings type
	 */
	static protected function set ( string $id, mixed $instance )
	{
		static::$items[static::$type] ??= [];

		if ( array_key_exists( $id, static::$items[static::$type] ) ) {
			return trigger_error( 'A settings "' . static::$type . '" with ID "' . $id . '" already exists.', E_USER_WARNING );
		}

		static::$items[static::$type][$id] = $instance;
	}

	/**
	 * Get a Settings type instance by id
	 *
	 * @param string $id      The unique id of this type
	 * @param mixed  $default The default value if this type is not found. Defaults to FALSE
	 * @return mixed
	 */
	static public function get ( string $id, mixed $default = FALSE )
	{
		if ( !array_key_exists( static::$type, static::$items ) || !array_key_exists( $id, static::$items[static::$type] ) ) {
			return $default;
		}

		return static::$items[static::$type][$id];
	}

	/**
	 * Get this Settings type
	 *
	 * @return string
	 */
	public function getType (): string
	{
		return static::$type;
	}

	/**
	 * Get this Settings id
	 *
	 * @return string
	 */
	public function getId ( ...$keys ): string
	{
		if ( !empty( $keys ) ) {
			if ( count( $keys ) === 1 && is_array( $keys[0] ) ) {
				$keys = $keys[0];
			}

			return implode( '_', [ $this->id, ...$keys ] );
		}

		return $this->id;
	}

	/**
	 * Get this Settings id
	 *
	 * @return string
	 */
	public function getName ( ...$names ): string
	{
		if ( !empty( $names ) ) {
			if ( count( $names ) === 1 && is_array( $names[0] ) ) {
				$names = $names[0];
			}

			return static::get_field_name( [ $this->id, ...$names ] );
		}

		return $this->id;
	}
}