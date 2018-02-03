![SeAT](http://i.imgur.com/aPPOxSK.png)
# installer

[![Code Climate](https://codeclimate.com/github/eveseat/eveapi/badges/gpa.svg)](https://codeclimate.com/github/eveseat/eveapi)
[![StyleCI](https://styleci.io/repos/73809164/shield?branch=master)](https://styleci.io/repos/73809164)

## This repository contains the SeAT Installer
Please use the main SeAT repository [here](https://github.com/eveseat/seat) for issues.

### building a new phar
This repository contains a `box.json` file which defines how the phar for the `seat` command should be built. Once you have installed all of the dependencies for this project using `composer install`, build yourself an updated phar with: `vendor/bin/box build`.

Output should be similar to:

```
$ vendor/bin/box build

    ____
   / __ )____  _  __
  / __  / __ \| |/_/
 / /_/ / /_/ />  <
/_____/\____/_/|_|


Box (repo)

? Removing the existing PHAR "/projects/seat/packages/eveseat/installer/dist/seat.phar"
Building the PHAR "/projects/seat/packages/eveseat/installer/dist/seat.phar"
? Setting replacement values
  + @package_version@: 8f7019c
? No compactor to register
? Adding binary files
    > No file found
? Adding files
    > 1015 file(s)
? Adding main file: /projects/seat/packages/eveseat/installer/bin/seat
? Generating new stub
? Compressing with the algorithm "GZ"
? Setting file permissions to 493
* Done.

 // Size: 1.23MB
 // Memory usage: 8.63MB (peak: 14.43MB), time: 31.29s
 ```
