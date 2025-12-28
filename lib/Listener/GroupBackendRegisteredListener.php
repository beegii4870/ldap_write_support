<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LdapUserWriteSupport\Listener;

use OCA\LdapUserWriteSupport\LDAPGroupManager;
use OCA\User_LDAP\Events\GroupBackendRegistered;
use OCP\App\IAppManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * @template-implements IEventListener<GroupBackendRegistered>
 */
class GroupBackendRegisteredListener implements IEventListener {
	/** @var IAppManager */
	private $appManager;

	/**
	 * Build the group backend registration listener.
	 *
	 * @param IAppManager $appManager App manager to verify dependencies.
	 * @param LDAPGroupManager $ldapGroupManager LDAP group manager plugin.
	 */
	public function __construct(
		IAppManager $appManager,
		private LDAPGroupManager $ldapGroupManager,
	) {
		$this->appManager = $appManager;
	}

	/**
	 * Register the LDAP group plugin when the core backend becomes available.
	 *
	 * @inheritDoc
	 */
	public function handle(Event $event): void {
		if (!$event instanceof GroupBackendRegistered
			|| !$this->appManager->isEnabledForUser('user_ldap')
		) {
			return;
		}
		$event->getPluginManager()->register($this->ldapGroupManager);
	}
}
