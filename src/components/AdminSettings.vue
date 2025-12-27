<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div id="ldap-user-write-support-admin-settings" class="section">
		<h2>{{ t('ldap_user_write_support', 'LDAP user write settings') }}</h2>
		<h3>{{ t('ldap_user_write_support', 'Permissions') }}</h3>
		<ul>
			<NcActionCheckbox :checked="switches.hasAvatarPermission"
				@change.stop.prevent="toggleSwitch('hasAvatarPermission', !switches.hasAvatarPermission)">
				{{ t('ldap_user_write_support', 'Allow users to set their avatar') }}
			</NcActionCheckbox>
			<NcActionCheckbox :checked="switches.hasPasswordPermission"
				@change.stop.prevent="toggleSwitch('hasPasswordPermission', !switches.hasPasswordPermission)">
				{{ t('ldap_user_write_support', 'Allow users to set their password') }}
			</NcActionCheckbox>
			<NcActionCheckbox :checked="switches.useUnicodePassword"
				:title="t('ldap_user_write_support', 'If the server does not support the modify password extended operation use the `unicodePwd` instead of the `userPassword` attribute for setting the password')"
				@change.stop.prevent="toggleSwitch('useUnicodePassword', !switches.useUnicodePassword)">
				{{ t('ldap_user_write_support', 'Use the `unicodePwd` attribute for setting the user password') }}
			</NcActionCheckbox>
		</ul>
	</div>
</template>

<script>
import NcActionCheckbox from '@nextcloud/vue/dist/Components/NcActionCheckbox.js'
import { showError } from '@nextcloud/dialogs'
import i10n from '../mixins/i10n.js'

import '@nextcloud/dialogs/style.css'

export default {
	name: 'AdminSettings',
	components: {
		NcActionCheckbox,
	},
	mixins: [i10n],
	props: {
		switches: {
			required: true,
			type: Object,
		},
	},
	/**
	 * Initialize reactive state based on the provided switches.
	 *
	 * @returns {{ checkboxes: Object }}
	 */
	data() {
		return {
			checkboxes: { ...this.switches },
		}
	},
	methods: {
		/**
		 * Persist a checkbox switch into app config.
		 *
		 * @param {string} prefKey Config key to set.
		 * @param {boolean} state Enabled state.
		 * @param {string} appId App ID to write configuration to.
		 */
		toggleSwitch(prefKey, state, appId = 'ldap_user_write_support') {
			this.checkboxes[prefKey] = state
			let value = (state | 0).toString()
			if (appId === 'core') {
				// the database key has a slighlty different style, need to transform
				prefKey = 'newUser.' + prefKey.charAt(7).toLowerCase() + prefKey.slice(8)
				value = value === '1' ? 'yes' : 'no'
			}

			OCP.AppConfig.setValue(appId, prefKey, value, {
				error: () => showError(t('ldap_user_write_support', 'Failed to set switch.')),
			})
		},
	},
}
</script>
<style lang="scss">
#ldap-user-write-support-admin-settings {
}
</style>
