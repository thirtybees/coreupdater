# Core Updater

This module brings the tools for keeping your shop installation up to date.

## Description

This module is designed to be a breeze for non-technical users as well as a versatile tool for thirty bees experts. Setting it to auto-mode keeps the shop installation up do date without further interaction. Manual updates are a matter of only a few clicks. One can choose between stable and development releases. One can even update to specific Git commits, e.g. to test bug fixes. And the best of all of this: each up- or downgrade can be rolled back in a second.

## License

This software is published under the [Academic Free License 3.0](https://opensource.org/licenses/afl-3.0.php)

## Contributing

thirty bees modules are Open Source extensions to the thirty bees e-commerce solution. Everyone is welcome and even encouraged to contribute with their own improvements.

For details, see [CONTRIBUTING.md](https://github.com/thirtybees/thirtybees/blob/1.0.x/CONTRIBUTING.md) in the thirty bees core repository.

## Packaging

To build a package for the thirty bees distribution machinery or suitable for importing it into a shop, run `tools/buildmodule.sh` of the thirty bees core repository from inside the module root directory.

For module development, one clones this repository into `modules/` of the shop, alongside the other modules. It should work fine without packaging.

## Roadmap

#### Short Term

* None currently.

#### Long Term

* Implement applying single commits.
* Instead of doing updates by exchanging files, try to do them by applying patches. This should help preserving manual code changes. It also needs instrumentation to deal with conflicts.
