<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LdapUserWriteSupport\Settings;

use OCA\LdapUserWriteSupport\AppInfo\Application;
use OCA\LdapUserWriteSupport\Service\Configuration;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Settings\ISettings;
use OCP\Util;

class Admin implements ISettings {
	/** @var IInitialState */
	private $initialStateService;

	/**
	 * Build the admin settings service.
	 *
	 * @param IInitialState $initialStateService Initial state provider.
	 * @param Configuration $config App configuration.
	 */
	public function __construct(
		IInitialState $initialStateService,
		private Configuration $config,
	) {
		$this->initialStateService = $initialStateService;
	}

	/**
	 * Build the admin settings form response.
	 *
	 * @return TemplateResponse returns the instance with all parameters set, ready to be rendered
	 * @since 9.1
	 */
	public function getForm() {
		$this->initialStateService->provideInitialState(
			'switches',
			[
				'hasAvatarPermission' => $this->config->hasAvatarPermission(),
				'hasPasswordPermission' => $this->config->hasPasswordPermission(),
				'useUnicodePassword' => $this->config->useUnicodePassword(),
			]
		);

		Util::addScript(Application::APP_ID, 'ldap_user_write_support-admin-settings');
		Util::addStyle(Application::APP_ID, 'ldap_user_write_support-admin-settings');

		return new TemplateResponse(Application::APP_ID, 'settings-admin');
	}

	/**
	 * Provide the section ID for the settings page.
	 *
	 * @return string the section ID, e.g. 'sharing'
	 * @since 9.1
	 */
	public function getSection() {
		return 'ldap';
	}

	/**
	 * Provide the priority for the settings page placement.
	 *
	 * @return int whether the form should be rather on the top or bottom of
	 *             the admin section. The forms are arranged in ascending order of the
	 *             priority values. It is required to return a value between 0 and 100.
	 *
	 * E.g.: 70
	 * @since 9.1
	 */
	public function getPriority() {
		return 35;
	}
}
