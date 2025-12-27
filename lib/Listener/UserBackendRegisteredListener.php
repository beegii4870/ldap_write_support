<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LdapUserWriteSupport\Listener;

use OCA\LdapUserWriteSupport\LDAPUserManager;
use OCA\User_LDAP\Events\UserBackendRegistered;
use OCP\App\IAppManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * @template-implements IEventListener<UserBackendRegistered>
 */
class UserBackendRegisteredListener implements IEventListener {
	/** @var IAppManager */
	private $appManager;

	/**
	 * Build the user backend registration listener.
	 *
	 * @param IAppManager $appManager App manager to verify dependencies.
	 * @param LDAPUserManager $ldapUserManager LDAP user manager plugin.
	 */
	public function __construct(
		IAppManager $appManager,
		private LDAPUserManager $ldapUserManager,
	) {
		$this->appManager = $appManager;
	}

	/**
	 * Register the LDAP user plugin when the core backend becomes available.
	 *
	 * @inheritDoc
	 */
	public function handle(Event $event): void {
		if (!$event instanceof UserBackendRegistered
			|| !$this->appManager->isEnabledForUser('user_ldap')
		) {
			return;
		}
		$event->getPluginManager()->register($this->ldapUserManager);
	}
}
