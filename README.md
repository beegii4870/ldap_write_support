<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-FileCopyrightText: 2017 Cooperativa EITA <eita.org.br>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# 👥🖎 LDAP User Write Support

[![REUSE status](https://api.reuse.software/badge/github.com/nextcloud/ldap_user_write_support)](https://api.reuse.software/info/github.com/nextcloud/ldap_user_write_support)

Update LDAP user attributes from within Nextcloud.

* 📛 **Update details:** display name, email address and avatars
* 🔐 **Passwords:** update LDAP passwords when permitted
* ⚙️ **Integrated**: works in the known Nextcloud users page

## Installation

This app requires the LDAP backend being enabled and configured, since it is a plugin to it. Find it on the app store!

## Beware of the dog

* LDAP write support relies on the core LDAP backend configuration and permissions. Make sure the LDAP server allows the updates you enable.

## 🏗 Development setup

1. ☁ Clone this app into the `apps` folder of your Nextcloud: `git clone https://github.com/nextcloud/ldap_user_write_support.git`
2. 👩‍💻 In the folder of the app, run the command `npm i` to install dependencies and `npm run build` to build the Javascript
3. ✅ Enable the app through the app management of your Nextcloud
4. 🎉 Partytime! Help fix [some issues](https://github.com/nextcloud/ldap_user_write_support/issues) and [review pull requests](https://github.com/nextcloud/ldap_user_write_support/pulls) 👍
