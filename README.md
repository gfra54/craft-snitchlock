# SnitchLock plugin for Craft CMS 3.x

SnitchLock is a fork from Snitch by Marion Newlevant. It watches element editors (entry, category, global set, user, etc.) and field editors, and lets you know when someone is editing the same thing at the same time, and locks the element for writing.

## Installation

To install SnitchLock, follow these steps: https://craftcms.stackexchange.com/a/23810/10476

SnitchLock works on Craft 3.x.


## Configuring SnitchLock

The default configuration can be overridden with a config file in `craft/config/snitchLock.php`. This is a standard Craft config file, with the usual multienvironment support. The configurable values are:

- `serverPollInterval`: interval (in seconds) for polling server to look for newly arrived conflicts. Default value: `2`. Minimum value 1, maximum value 5.
- `messageTemplate`: text for the warning banner. This is parsed as twig, with `user` the user who may be in conflict. Default value is: `May also be edited by: <a href="mailto:{{user.email}}">{{user.username}}</a>.`. This will generate a `mailto` link to the user. You can change this to enable Slack messaging, or what-have-you.
- `elementInputIdSelector`: the css selector for identifying the hidden inputs which indicate an element edit window or modal element edit window.
- `fieldInputIdSelector`: css selector for identifying the hidden inputs which indicate a field edit window.

The visual look of the warning banners can be modifed with the [cpcss](https://plugins.craftcms.com/cp-css) plugin.

## How it works.

Javascript (and css) is added to every backend page. That javascript fetches the configuration values, and then starts polling. Every 2 seconds, it looks for any edit forms on the page, and if it finds such a form, reports via ajax the type and the id of the element that the edit form is editing. In return, it is passed a list of possible collisions.

On the server, a database table (snitchLock_collisions) records
- the user
- the type of the element being edited
- the id of the element being edited
- when the element was last reported being edited by this user.

When the ajax call arrives reporting that an element is being edited, these things happen:

1. Any report that has not been updated in 10 poll intervals is removed from the snitchLock_collisions table. It is no longer being edited.
2. Either create a new report for this user/element type/element id, or update the time on the existing one
3. Look for any edit reports of this element by other users.
4. Return the user data from these reports. These will be other people who may be editing the element.

## Issues

SnitchLock will fail to notice when one person is editing a Commerce product, and another is editing a variant of that product through a modal.

