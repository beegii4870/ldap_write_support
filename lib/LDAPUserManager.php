<?php

/**
 * SPDX-FileCopyrightText: 2019-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2017-2019 Cooperativa EITA <eita.org.br>
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace OCA\LdapUserWriteSupport;

use Exception;
use LDAP\Connection;
use OC\ServerNotAvailableException;
use OC\User\Backend;
use OC_User;
use OCA\LdapUserWriteSupport\AppInfo\Application;
use OCA\LdapUserWriteSupport\Service\Configuration;
use OCA\User_LDAP\Exceptions\ConstraintViolationException;
use OCA\User_LDAP\ILDAPUserPlugin;
use OCA\User_LDAP\IUserLDAP;
use OCP\HintException;
use OCP\IImage;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\LDAP\IDeletionFlagSupport;
use OCP\LDAP\ILDAPProvider;
use Psr\Log\LoggerInterface;

class LDAPUserManager implements ILDAPUserPlugin {
	/** @var ILDAPProvider */
	private $ldapProvider;

	/** @var IUserManager */
	private $userManager;
	/** @var IL10N */
	private $l10n;

	/**
	 * Build the LDAP user manager plugin.
	 *
	 * @param IUserManager $userManager User manager to register hooks with.
	 * @param LDAPConnect $ldapConnect LDAP connection helper.
	 * @param ILDAPProvider $LDAPProvider LDAP provider from the core backend.
	 * @param Configuration $configuration App configuration for permissions.
	 * @param IL10N $l10n Localization service.
	 * @param LoggerInterface $logger Logger for LDAP operations.
	 */
	public function __construct(
		IUserManager $userManager,
		private LDAPConnect $ldapConnect,
		ILDAPProvider $LDAPProvider,
		private Configuration $configuration,
		IL10N $l10n,
		private LoggerInterface $logger,
	) {
		$this->userManager = $userManager;
		$this->ldapProvider = $LDAPProvider;
		$this->l10n = $l10n;

		$this->userManager->listen('\OC\User', 'changeUser', [$this, 'changeUserHook']);
		$this->makeLdapBackendFirst();
	}

	/**
	 * Returns the supported actions for the LDAP user plugin.
	 *
	 * @return int Bitwise-or'ed actions supported by this backend.
	 */
	public function respondToActions() {
		$setPassword = $this->canSetPassword() && !$this->ldapConnect->hasPasswordPolicy()
			? Backend::SET_PASSWORD
			: 0;

		return Backend::SET_DISPLAYNAME |
			Backend::PROVIDE_AVATAR |
			$setPassword;
	}

	/**
	 * Update the LDAP display name for a user.
	 *
	 * @param string $uid user ID of the user
	 * @param string $displayName new user's display name
	 * @return string
	 * @throws HintException
	 * @throws ServerNotAvailableException
	 */
	public function setDisplayName($uid, $displayName) {
		$userDN = $this->getUserDN($uid);

		$connection = $this->ldapProvider->getLDAPConnection($uid);

		try {
			$displayNameField = $this->ldapProvider->getLDAPDisplayNameField($uid);
			// The LDAP backend supports a second display name field, but it is
			// not exposed at this time. So it is just ignored for now.
		} catch (Exception $e) {
			throw new HintException(
				'Corresponding LDAP User not found',
				$this->l10n->t('Could not find related LDAP entry')
			);
		}

		if (!is_resource($connection) && !is_object($connection)) {
			$this->logger->debug('LDAP resource not available', ['app' => Application::APP_ID]);
			throw new ServerNotAvailableException('LDAP server is not available');
		}
		try {
			$entry = [
				$displayNameField => $displayName,
				'sn' => $displayName,
			];
			if (ldap_mod_replace($connection, $userDN, $entry)) {
				return $displayName;
			}
			throw new HintException('Failed to set display name');
		} catch (ConstraintViolationException $e) {
			throw new HintException(
				$e->getMessage(),
				$this->l10n->t('DisplayName change rejected'),
				$e->getCode()
			);
		}
	}

	/**
	 * Check whether a user can update their LDAP avatar through Nextcloud.
	 *
	 * @param string $uid the Nextcloud user name
	 * @return bool either the user can or cannot
	 */
	public function canChangeAvatar($uid) {
		return $this->configuration->hasAvatarPermission();
	}

	/**
	 * Save a Nextcloud avatar into LDAP, or remove jpegPhoto when the avatar is cleared.
	 *
	 * @param IUser $user
	 */
	public function changeAvatar($user): void {
		try {
			$userDN = $this->getUserDN($user->getUID());
		} catch (Exception) {
			return;
		}

		/** @var IImage $avatar */
		$avatar = $user->getAvatarImage(-1);
		$connection = $this->ldapProvider->getLDAPConnection($user->getUID());
		if ($avatar) {
			$data = $avatar->data();
			ldap_mod_replace($connection, $userDN, ['jpegphoto' => $data]);
			return;
		}

		ldap_mod_del($connection, $userDN, ['jpegphoto' => []]);
	}

	/**
	 * Save a Nextcloud email address into LDAP.
	 *
	 * @param IUser $user
	 * @throws Exception
	 */
	public function changeEmail(IUser $user, string $newEmail): void {
		try {
			$userDN = $this->getUserDN($user->getUID());
		} catch (Exception) {
			return;
		}

		$emailField = $this->ldapProvider->getLDAPEmailField($user->getUID());
		$connection = $this->ldapProvider->getLDAPConnection($user->getUID());
		ldap_mod_replace($connection, $userDN, [$emailField => $newEmail]);
	}

	/**
	 * Create a new LDAP user (disabled in this app).
	 *
	 * @param string $uid The username of the user to create.
	 * @param string $password The password of the new user.
	 * @return bool Always false to indicate creation is disabled.
	 */
	public function createUser($uid, $password): bool {
		$this->logger->notice(
			'LDAP user creation is disabled for {uid}',
			[
				'app' => Application::APP_ID,
				'uid' => $uid,
			]
		);

		return false;
	}

	/**
	 * Delete a user from LDAP if the backend supports deletion.
	 *
	 * @param $uid
	 * @return bool
	 */
	public function deleteUser($uid): bool {
		$connection = $this->ldapProvider->getLDAPConnection($uid);
		$userDN = $this->getUserDN($uid);
		$user = $this->userManager->get($uid);
		if ($res = ldap_delete($connection, $userDN)) {
			$message = 'Delete LDAP user (isDeleted): ' . $uid;
			$this->logger->notice($message, ['app' => Application::APP_ID]);
			if (
				$this->ldapProvider instanceof IDeletionFlagSupport
				&& $user instanceof IUser
			) {
				$this->ldapProvider->flagRecord($uid);
			} else {
				$this->logger->warning(
					'Could not run delete process on {uid}',
					['app' => Application::APP_ID, 'uid' => $uid]
				);
			}
		} else {
			$errno = ldap_errno($connection);
			if ($errno === 0x20) { #LDAP_NO_SUCH_OBJECT
				$message = 'Delete LDAP user {uid}: object not found. Is already deleted? Assuming YES';
				$res = true;
			} else {
				$message = 'Unable to delete LDAP user {uid}';
			}
			$this->logger->notice($message, ['app' => Application::APP_ID, 'uid' => $uid]);
		}
		ldap_close($connection);
		return $res;
	}

	/**
	 * Check whether a user can change their password via Nextcloud.
	 *
	 * @return bool either the user can or cannot
	 */
	public function canSetPassword(): bool {
		return $this->configuration->hasPasswordPermission();
	}

	/**
	 * Change a user's password in LDAP.
	 *
	 * @param string $uid The username
	 * @param string $password The new password
	 * @return bool
	 *
	 * Change the password of a user
	 */
	public function setPassword($uid, $password) {
		$connection = $this->ldapProvider->getLDAPConnection($uid);
		$userDN = $this->getUserDN($uid);

		return $this->handleSetPassword($userDN, $password, $connection);
	}

	/**
	 * Get the user's home directory (not implemented).
	 *
	 * @param string $uid the username
	 * @return bool
	 */
	public function getHome($uid) {
		// Not implemented
		return false;
	}

	/**
	 * Get display name of the user (not implemented).
	 *
	 * @param string $uid user ID of the user
	 * @return string display name
	 */
	public function getDisplayName($uid) {
		// Not implemented
		return $uid;
	}

	/**
	 * Count the number of users (not implemented).
	 *
	 * @return int|bool
	 */
	public function countUsers() {
		// Not implemented
		return false;
	}

	/**
	 * Reorder the user backends to ensure LDAP comes first.
	 */
	public function makeLdapBackendFirst(): void {
		$backends = $this->userManager->getBackends();
		$otherBackends = [];
		$this->userManager->clearBackends();
		foreach ($backends as $backend) {
			if ($backend instanceof IUserLDAP) {
				OC_User::useBackend($backend);
			} else {
				$otherBackends[] = $backend;
			}
		}

		#insert other backends: database, etc
		foreach ($otherBackends as $backend) {
			OC_User::useBackend($backend);
		}
	}

	/**
	 * Handle user change events coming from Nextcloud.
	 *
	 * @throws Exception
	 */
	public function changeUserHook(IUser $user, string $feature, $attr1, $attr2): void {
		switch ($feature) {
			case 'avatar':
				$this->changeAvatar($user);
				break;
			case 'eMailAddress':
				//attr1 = new email ; attr2 = old email
				$this->changeEmail($user, $attr1);
				break;
		}
	}

	/**
	 * Resolve the LDAP distinguished name for a user ID.
	 *
	 * @param string $uid User ID to resolve.
	 */
	private function getUserDN($uid): string {
		return $this->ldapProvider->getUserDN($uid);
	}

	/**
	 * Handle setting a user's password, using exop when available.
	 *
	 * @param string $userDN The username
	 * @param string $password The new password
	 * @param Connection $connection The LDAP connection to use
	 * @return bool
	 *
	 * Change the password of a user
	 */
	private function handleSetPassword(string $userDN, string $password, Connection $connection): bool {
		try {
			$ret = false;

			// try ldap_exop_passwd first
			if ($this->ldapConnect->hasPasswdExopSupport($connection)) {
				if (ldap_exop_passwd($connection, $userDN, '', $password) === true) {
					// `ldap_exop_passwd` is either FALSE or the password, in the later case return TRUE
					return true;
				}

				$message = 'Failed to set password for user {dn} using ldap_exop_passwd';
				$this->logger->error($message, [
					'ldap_error' => ldap_error($connection),
					'app' => Application::APP_ID,
					'dn' => $userDN,
				]);
			} else {
				// Use ldap_mod_replace in case the server does not support exop_passwd
				$entry = [];
				if ($this->configuration->useUnicodePassword()) {
					$entry['unicodePwd'] = iconv('UTF-8', 'UTF-16LE', '"' . $password . '"');
				} else {
					$entry['userPassword'] = $password;
				}

				if (ldap_mod_replace($connection, $userDN, $entry)) {
					return true;
				}
				
				$message = 'Failed to set password for user {dn} using ldap_mod_replace';
				$this->logger->error($message, [
					'ldap_error' => ldap_error($connection),
					'app' => Application::APP_ID,
					'dn' => $userDN,
				]);
			}
			return false;
		} catch (\Exception $e) {
			$this->logger->error('Exception occured while setting the password of user {dn}', [
				'app' => Application::APP_ID,
				'exception' => $e,
				'dn' => $userDN,
			]);
			return false;
		}
	}
}
