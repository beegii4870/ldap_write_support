<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\LdapUserWriteSupport\AppInfo;

use OCA\LdapUserWriteSupport\Listener\GroupBackendRegisteredListener;
use OCA\LdapUserWriteSupport\Listener\UserBackendRegisteredListener;
use OCA\User_LDAP\Events\GroupBackendRegistered;
use OCA\User_LDAP\Events\UserBackendRegistered;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
	/**
	 * The app identifier used for configuration, scripts, and logging.
	 */
	public const APP_ID = 'ldap_user_write_support';

	/**
	 * Create the application bootstrap instance.
	 *
	 * @param array $urlParams URL parameters provided by Nextcloud.
	 */
	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	/**
	 * Register event listeners for LDAP backend registration.
	 *
	 * @param IRegistrationContext $context Registration context.
	 */
	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(UserBackendRegistered::class, UserBackendRegisteredListener::class);
		$context->registerEventListener(GroupBackendRegistered::class, GroupBackendRegisteredListener::class);
	}

	/**
	 * Boot the application (no-op for this app).
	 *
	 * @param IBootContext $context Boot context.
	 */
	public function boot(IBootContext $context): void {
	}
}
