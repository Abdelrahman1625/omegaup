<?php
# ###################################
# GLOBAL CONFIG
# ###################################
define('OMEGAUP_ROOT', '/opt/omegaup/frontend');

# ####################################
# DATABASE CONFIG
# ####################################
define('OMEGAUP_DB_USER',				'omegaup');
define('OMEGAUP_DB_PASS',				'');
define('OMEGAUP_DB_HOST',				'localhost');
define('OMEGAUP_DB_NAME',				'omegaup');
define('OMEGAUP_DB_DRIVER',				'mysqlt');
define('OMEGAUP_DB_DEBUG',				false);
define('OMEGAUP_MD5_SALT',				'omegaup');

define('OMEGAUP_SLAVE_DB_USER',				'omegaup');
define('OMEGAUP_SLAVE_DB_PASS',				'');
define('OMEGAUP_SLAVE_DB_HOST',				'8.8.8.8');
define('OMEGAUP_SLAVE_DB_NAME',				'omegaup');
define('OMEGAUP_SLAVE_DB_DRIVER',			'mysqlt');

# ####################################
# LOG CONFIG
# ####################################
define('OMEGAUP_LOG_TO_FILE',				true);
define('OMEGAUP_LOG_DB_QUERYS',				false);
define('OMEGAUP_LOG_LEVEL',				"info");
define('OMEGAUP_LOG_FILE',				'/var/log/omegaup/omegaup.log');
define('OMEGAUP_EXPIRE_TOKEN_AFTER',			'8 HOUR');

# ####################################
# GRADER CONFIG
# ####################################
define('OMEGAUP_GRADER_URL',				'https://localhost:21680/grade/');
define('OMEGAUP_SSLCERT_URL',				'/opt/omegaup/frontend/omegaup.pem');
define('OMEGAUP_CACERT_URL',				'/opt/omegaup/frontend/omegaup.pem');
define('RUNS_PATH',					'/var/lib/omegaup/submissions');
define('PROBLEMS_PATH',					'/var/lib/omegaup/problems');
define('BIN_PATH',					'/opt/omegaup/bin');
define('IMAGES_PATH',					'/opt/omegaup/frontend/www/img/');
define('IMAGES_URL_PATH',				'/img/');
define('OMEGAUP_GRADER_CONFIG_PATH',			'/opt/omegaup/grader/omegaup.conf');
define('OMEGAUP_GRADER_RELOAD_CONFIG_URL',		'https://localhost:21680/reload-config/');
define('OMEGAUP_GRADER_STATUS_URL',			'https://localhost:21680/status/');
define('OMEGAUP_ENABLE_REJUDGE_ON_PROBLEM_UPDATE',	true);

# ####################################
# FACEBOOK LOGIN CONFIG
# ####################################
define('OMEGAUP_FB_APPID',				'xxxxx');
define('OMEGAUP_FB_SECRET',				'xxxxx');

# ####################################
# GOOGLE ANALYTICS
# ####################################
define('OMEGAUP_GA_TRACK',				false);
define('OMEGAUP_GA_ID',					'xxxxx');

# ####################################
# EMAIL CONFIG
# ####################################
define('OMEGAUP_EMAIL_SEND_EMAILS',			false);
define('OMEGAUP_FORCE_EMAIL_VERIFICATION',		false);
define('OMEGAUP_EMAIL_SMTP_HOST',			'xxxx');
define('OMEGAUP_EMAIL_SMTP_USER',			'xxxx');
define('OMEGAUP_EMAIL_SMTP_PASSWORD',			'xxxx');
define('OMEGAUP_EMAIL_SMTP_PORT',			'xxxx');
define('OMEGAUP_EMAIL_SMTP_FROM',			'xxxx');
define('OMEGAUP_EMAIL_MAILCHIMP_ENABLE', false);
define('OMEGAUP_EMAIL_MAILCHIMP_API_KEY', 'xxxx');
define('OMEGAUP_EMAIL_MAILCHIMP_LIST_ID', 'xxxx');

# #########################
# CACHE CONFIG
# #########################
define('APC_USER_CACHE_ENABLED',			true);
define('APC_USER_CACHE_CONTEST_INFO_TIMEOUT',		10);
define('APC_USER_CACHE_PROBLEM_STATEMENT_TIMEOUT',	60); // in seconds
define('APC_USER_CACHE_PROBLEM_STATS_TIMEOUT',		 0); // in seconds
define('APC_USER_CACHE_SESSION_TIMEOUT', 8 * 3600); // seconds, match OMEGAUP_EXPIRE_TOKEN_AFTER
define('OMEGAUP_SESSION_CACHE_ENABLED', true);

# #########################
# SMARTY
# #########################
define('SMARTY_CACHE_DIR',				'/var/tmp');
define('IS_TEST',					FALSE);
