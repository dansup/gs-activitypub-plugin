# ActivityPub plugin for GNU Social 1.0 Alpha
2018

(c) Free Software Foundation, Inc

This is the README file for GNU Social's ActivityPub plugin.
It includes general information about the plugin.

## About

This plugin adds [ActivityPub](https://www.w3.org/TR/activitypub/) support to 
GNU Social.

## Setup

1. Put all files in /plugins/ActivityPub

2. Add `addPlugin('ActivityPub');` to your /config.php file.

3. For better performance consider both:
   - disabling checkschema (instructions in GNU social's config.php), but don't forget to run it when updating plugins (including ActivityPub plugin)
   - installing Chimo's Redis plugin

##  For testing, (shouldn't be used in production)

        composer install && vendor/bin/phpunit

## Built With

* [PHPUnit](https://phpunit.de/) - Automated tests

## Contributing

Please read [CONTRIBUTING.md](CONTRIBUTING.md) for details on our code of conduct, and the process for submitting pull requests to us.

## Versioning

We use [SemVer](http://semver.org/) for versioning. For the versions available, see the [tags on this repository](https://git.gnu.io/gnu/GS-ActivityPub-Plugin/tags). 

## Credits

* **[Diogo Cordeiro](https://www.diogo.site/)**
* **[Daniel Supernault](https://github.com/dansup)**

See also the list of [contributors](https://git.gnu.io/gnu/GS-ActivityPub-Plugin/contributors) who participated in this project.

## License

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Affero General Public License as
published by the Free Software Foundation, either version 3 of the
License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but
WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public
License along with this program, in the file "COPYING".  If not, see
<http://www.gnu.org/licenses/>.

    IMPORTANT NOTE: The GNU Affero General Public License (AGPL) has
    *different requirements* from the "regular" GPL. In particular, if
    you make modifications to the plugin source code on your server,
    you *MUST MAKE AVAILABLE* the modified version of the source code
    to your users under the same license. This is a legal requirement
    of using the software, and if you do not wish to share your
    modifications, *YOU MAY NOT USE THIS PLUGIN*.
