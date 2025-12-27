/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { loadState } from '@nextcloud/initial-state'
import Vue from 'vue'

import AdminSettings from './components/AdminSettings.vue'

// eslint-disable-next-line import/no-unresolved, n/no-missing-import
import 'vite/modulepreload-polyfill'

const View = Vue.extend(AdminSettings)
const AppID = 'ldap_user_write_support'

new View({
	propsData: {
		switches: loadState(AppID, 'switches'),
	},
}).$mount('#ldap-user-write-support-admin-settings')
