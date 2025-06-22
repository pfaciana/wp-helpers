<?php

namespace RWP\Hooks;

class LocalizeScripts
{
	protected $key = '';

	public function __construct ( $key = '' )
	{
		$this->key = trim( preg_replace( '/[^\w]/', '', $key ) );

		add_action( 'wp_head', [ $this, 'apply_header_filters' ] );
		add_action( 'wp_footer', [ $this, 'apply_footer_filters' ] );

		add_action( 'admin_head', [ $this, 'apply_admin_header_filters' ] );
		add_action( 'admin_footer', [ $this, 'apply_admin_footer_filters' ] );
	}

	public function apply_header_filters ()
	{
		$this->apply_filters( 'header' );
	}

	public function apply_footer_filters ()
	{
		$this->apply_filters( 'footer' );
	}

	public function apply_admin_header_filters ()
	{
		$this->apply_filters( 'header', TRUE );
	}

	public function apply_admin_footer_filters ()
	{
		$this->apply_filters( 'footer', TRUE );
	}

	protected function apply_filters ( $location, $admin = FALSE )
	{
		$obj = apply_filters( "localize_js", [], $this->key, $location, $admin );
		$obj = apply_filters( "localize_js_{$this->key}", $obj, $location, $admin );
		$obj = apply_filters( "localize_js_{$this->key}_{$location}", $obj, $admin );

		if ( empty( $obj ) ) {
			return;
		}

		$obj = json_encode( $obj );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return;
		}

		$src   = !empty( $this->key ) ? "window.{$this->key}" : 'window';
		$setup = "{$src} = {$src} || {}";

		?>
		<script type="application/javascript">
			<?=$setup?>;
			Object.assign(<?=$src?>, <?=$obj?>);
		</script>
		<?php
	}

	/**
	 * Prevents the class from being cloned.
	 */
	private function __clone () { }
}