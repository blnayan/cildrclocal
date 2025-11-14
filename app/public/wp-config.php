<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'local' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'root' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',          'm7+Dht0PI8;[~Wl!r<wKD Kz3>/vGMk[#Wr@Jr~M8c7b{5FVfo`~A1VjWG .z_[l' );
define( 'SECURE_AUTH_KEY',   'cd9[,e?tRXAZw|@=@#{j!GaTBQik*J[h_*xnlEqtt6-hkD(Kt2rP5_meTn|/^*+ ' );
define( 'LOGGED_IN_KEY',     'M$w?[dq/&$*Pw{esv&Dmc?O|:^GZ%#OoX6dTAo4%k;()3XVXFl:6,P^WJs8lF`w;' );
define( 'NONCE_KEY',         ' SPh:jqcie<.F)Qo|irkelOa=7/.#c6#XZyM|{;@jvAox6&J_,,R(/VU`WHp,qGB' );
define( 'AUTH_SALT',         'w~cp9BZXvLVe.Ee5N8BSI)i9!X-Z~$XA7VVD@bFG5]sPvet)I$!dDO;}UQKFoF>`' );
define( 'SECURE_AUTH_SALT',  'Xkr1yO?Q+}J01B_7I9oU8}iA,&ZR:9UmUDWUDPb<<]?},`qVELGVBU6cdW`]lL-N' );
define( 'LOGGED_IN_SALT',    'm|y4y++uI[74n<Coh{`jat1cB:BUgVrm-[^up`+M/}Mx}0%^T3pG#65~)}2kW_QU' );
define( 'NONCE_SALT',        'N8ZEN#zq4!BbKwBm RYWz_{M=9 @XyB#YE?:ll~>|zn,IXE,$$I/*R%x1*28lq8$' );
define( 'WP_CACHE_KEY_SALT', 'wtf(S-7,Mr5kc^,oSjD0u>_Q$kUTo{KN_+w}u_E8tqFON#/1OkqB?zlzC(lEPGvg' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */



/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

define( 'WP_ENVIRONMENT_TYPE', 'local' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
