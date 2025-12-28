<?php

/**
 * SPDX-FileCopyrightText: 2019-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2017-2019 Cooperativa EITA <eita.org.br>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LdapUserWriteSupport;

use Exception;
use OCA\LdapUserWriteSupport\AppInfo\Application;
use OCA\User_LDAP\Group_Proxy;
use OCA\User_LDAP\ILDAPGroupPlugin;
use OCP\GroupInterface;
use OCP\IGroupManager;
use OCP\LDAP\ILDAPProvider;
use Psr\Log\LoggerInterface;

class LDAPGroupManager implements ILDAPGroupPlugin {
	/** @var ILDAPProvider */
	private $ldapProvider;

	/** @var IGroupManager */
	private $groupManager;

	/**
	 * Build the LDAP group manager plugin.
	 *
	 * @param IGroupManager $groupManager Group manager to register backends with.
	 * @param LDAPConnect $ldapConnect LDAP connection helper.
	 * @param LoggerInterface $logger Logger for LDAP operations.
	 * @param ILDAPProvider $LDAPProvider LDAP provider from the core backend.
	 */
	public function __construct(
		IGroupManager $groupManager,
		private LDAPConnect $ldapConnect,
		private LoggerInterface $logger,
		ILDAPProvider $LDAPProvider,
	) {
		$this->groupManager = $groupManager;
		$this->ldapProvider = $LDAPProvider;

		if ($this->ldapConnect->groupsEnabled()) {
			$this->makeLdapBackendFirst();
		}
	}

	/**
	 * Return the supported group actions for this backend.
	 *
	 * @return int Bitwise-or'ed actions.
	 */
	public function respondToActions() {
		if (!$this->ldapConnect->groupsEnabled()) {
			return 0;
		}
		return GroupInterface::DELETE_GROUP |
			GroupInterface::ADD_TO_GROUP |
			GroupInterface::REMOVE_FROM_GROUP;
	}

	/**
	 * Create a new LDAP group (disabled for this app).
	 *
	 * @param string $gid Group ID to create.
	 * @return string|null Always null because group creation is disabled.
	 */
	public function createGroup($gid) {
		$this->logger->notice(
			'LDAP group creation is disabled for {gid}',
			[
				'app' => Application::APP_ID,
				'gid' => $gid,
			]
		);
		return null;
	}

	/**
	 * Delete a group from LDAP.
	 *
	 * @param string $gid gid of the group to delete
	 * @return bool
	 * @throws Exception
	 */
	public function deleteGroup($gid) {
		$connection = $this->ldapProvider->getGroupLDAPConnection($gid);
		$groupDN = $this->ldapProvider->getGroupDN($gid);

		if (!$ret = ldap_delete($connection, $groupDN)) {
			$message = 'Unable to delete LDAP Group: ' . $gid;
			$this->logger->error($message, ['app' => Application::APP_ID]);
		} else {
			$message = 'Delete LDAP Group: ' . $gid;
			$this->logger->notice($message, ['app' => Application::APP_ID]);
		}
		return $ret;
	}

	/**
	 * Add an LDAP user to an LDAP group.
	 *
	 * @param string $uid Name of the user to add to group
	 * @param string $gid Name of the group in which add the user
	 * @return bool
	 *
	 * Adds a LDAP user to a LDAP group.
	 * @throws Exception
	 */
	public function addToGroup($uid, $gid) {
		$connection = $this->ldapProvider->getGroupLDAPConnection($gid);
		$groupDN = $this->ldapProvider->getGroupDN($gid);

		$entry = [];
		switch ($this->ldapProvider->getLDAPGroupMemberAssoc($gid)) {
			case 'memberUid':
				$entry['memberuid'] = $uid;
				break;
			case 'uniqueMember':
				$entry['uniquemember'] = $this->ldapProvider->getUserDN($uid);
				break;
			case 'member':
				$entry['member'] = $this->ldapProvider->getUserDN($uid);
				break;
			case 'gidNumber':
				throw new Exception('Cannot add to group when gidNumber is used as relation');
				break;
		}

		if (!$ret = ldap_mod_add($connection, $groupDN, $entry)) {
			$message = 'Unable to add user ' . $uid . ' to group ' . $gid;
			$this->logger->error($message, ['app' => Application::APP_ID]);
		} else {
			$message = 'Add user: ' . $uid . ' to group: ' . $gid;
			$this->logger->notice($message, ['app' => Application::APP_ID]);
		}
		return $ret;
	}

	/**
	 * Remove an LDAP user from an LDAP group.
	 *
	 * @param string $uid Name of the user to remove from group
	 * @param string $gid Name of the group from which remove the user
	 * @return bool
	 *
	 * removes the user from a group.
	 * @throws Exception
	 */
	public function removeFromGroup($uid, $gid) {
		$connection = $this->ldapProvider->getGroupLDAPConnection($gid);
		$groupDN = $this->ldapProvider->getGroupDN($gid);

		$entry = [];
		switch ($this->ldapProvider->getLDAPGroupMemberAssoc($gid)) {
			case 'memberUid':
				$entry['memberuid'] = $uid;
				break;
			case 'uniqueMember':
				$entry['uniquemember'] = $this->ldapProvider->getUserDN($uid);
				break;
			case 'member':
				$entry['member'] = $this->ldapProvider->getUserDN($uid);
				break;
			case 'gidNumber':
				throw new Exception('Cannot remove from group when gidNumber is used as relation');
		}

		if (!$ret = ldap_mod_del($connection, $groupDN, $entry)) {
			$message = 'Unable to remove user: ' . $uid . ' from group: ' . $gid;
			$this->logger->error($message, ['app' => Application::APP_ID]);
		} else {
			$message = 'Remove user: ' . $uid . ' from group: ' . $gid;
			$this->logger->notice($message, ['app' => Application::APP_ID]);
		}
		return $ret;
	}


	/**
	 * Count users in a group (not implemented).
	 *
	 * @param string $gid Group ID.
	 * @param string $search Optional search string.
	 * @return bool
	 */
	public function countUsersInGroup($gid, $search = '') {
		return false;
	}

	/**
	 * Get details of a group (not implemented).
	 *
	 * @param string $gid Group ID.
	 * @return bool
	 */
	public function getGroupDetails($gid) {
		return false;
	}

	/**
	 * Check whether a group is backed by LDAP.
	 *
	 * @param string $gid Group ID.
	 * @return bool
	 */
	public function isLDAPGroup($gid): bool {
		try {
			return !empty($this->ldapProvider->getGroupDN($gid));
		} catch (Exception) {
			return false;
		}
	}

	/**
	 * Reorder group backends to ensure LDAP comes first.
	 */
	public function makeLdapBackendFirst(): void {
		$backends = $this->groupManager->getBackends();
		$otherBackends = [];
		$this->groupManager->clearBackends();
		foreach ($backends as $backend) {
			if ($backend instanceof Group_Proxy) {
				$this->groupManager->addBackend($backend);
			} else {
				$otherBackends[] = $backend;
			}
		}

		#insert other backends: database, etc
		foreach ($otherBackends as $backend) {
			$this->groupManager->addBackend($backend);
		}
	}
}
