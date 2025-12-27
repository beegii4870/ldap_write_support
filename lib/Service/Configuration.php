<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LdapUserWriteSupport\Service;

use OCA\LdapUserWriteSupport\AppInfo\Application;
use OCP\IConfig;

class Configuration {
	/** @var IConfig */
	private $config;

	/**
	 * Build the configuration wrapper around Nextcloud config.
	 *
	 * @param IConfig $config Nextcloud configuration service.
	 */
	public function __construct(IConfig $config) {
		$this->config = $config;
	}

	/**
	 * Whether LDAP avatars may be updated from Nextcloud.
	 */
	public function hasAvatarPermission(): bool {
		return $this->config->getAppValue(Application::APP_ID, 'hasAvatarPermission', '1') === '1';
	}

	/**
	 * Whether LDAP passwords may be updated from Nextcloud.
	 */
	public function hasPasswordPermission(): bool {
		return $this->config->getAppValue(Application::APP_ID, 'hasPasswordPermission', '1') === '1';
	}

	/**
	 * Whether to use the unicodePwd attribute when setting passwords.
	 */
	public function useUnicodePassword(): bool {
		return $this->config->getAppValue(Application::APP_ID, 'useUnicodePassword', '0') === '1';
	}
}
