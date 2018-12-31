<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'tauros');

/** MySQL database username */
define('DB_USER', 'root');

/** MySQL database password */
define('DB_PASSWORD', '');

/** MySQL hostname */
define('DB_HOST', 'localhost');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8mb4');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         '/#rfOcC&Gp29WJcT09Oav!PYw^J|9M*~5p=u_z5y3S7tuqA%:.rDoC6;u_.nFJDo');
define('SECURE_AUTH_KEY',  '}EceSSna1Fs~Nu3:e<hg2={.#?]Glg+F}[G1x^<fUqVypv1%k[;=tQ]E03zJdhF+');
define('LOGGED_IN_KEY',    '0#+fS=6`B/SSii|*Q_o8TPO];E85$_,$cc#_p%#pCD=tt$pIL}Gy0l{Y,;A]X!Im');
define('NONCE_KEY',        'I*+6Z)_-^Z|SX2n|RT+:^%L6T`8UTN#-l[cTtNmS4asl9H_XZRU(iN1?|#6 /<~o');
define('AUTH_SALT',        '4g,(CGw=Q nS/N2r5Yz4VZwk&TQ=Pi],?H-#Znk3$@2(EcT.jODDq0GY1eG(@U=i');
define('SECURE_AUTH_SALT', 'hNTS%;qPxQW`k[N/:<(@rb1^DyIYV7%b=9)`0|GaWz5[&|p4MDNg1S?aBVo8o^Uj');
define('LOGGED_IN_SALT',   '}CqNcv9j$][4+s9gl}RCttO8]zdNwS`,g!Rx^P]=/lp!uT{CLGQM:pWc?iK=?k| ');
define('NONCE_SALT',       'J!UgB)pG!$IE;-p[ !xqkcmNK6g,vgmg^H1]}iH{{foLN`bVn&1FJj%VLeVP:)se');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the Codex.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define('WP_DEBUG', false);

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
