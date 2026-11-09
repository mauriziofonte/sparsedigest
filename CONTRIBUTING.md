# Contributing

Contributions are **welcome** and will be fully **credited**.

Please read and understand the contribution guide before creating an issue or pull request.

## Etiquette

This project follows a [Code of Conduct](CODE_OF_CONDUCT.md). By participating, you are expected to uphold it.

This project is open source, and as such, the maintainers give their free time to build and maintain the source code
held within. They make the code freely available in the hope that it will be of use to other developers. It would be
extremely unfair for them to suffer abuse or anger for their hard work.

Please be considerate towards maintainers when raising issues or presenting pull requests. Let's show the
world that developers are civilized and selfless people.

It's the duty of the maintainer to ensure that all submissions to the project are of sufficient
quality to benefit the project. Many developers have different skillsets, strengths, and weaknesses. Respect the maintainer's decision, and do not be upset or abusive if your submission is not used.

## Viability

When requesting or submitting new features, first consider whether it might be useful to others. Open
source projects are used by many developers, who may have entirely different needs to your own. Think about
whether or not your feature is likely to be used by other users of the project.

## Procedure

Before filing an issue:

- Attempt to replicate the problem, to ensure that it wasn't a coincidental incident.
- Check to make sure your feature suggestion isn't already present within the project.
- Check the pull requests tab to ensure that the bug doesn't have a fix in progress.
- Check the pull requests tab to ensure that the feature isn't already in progress.

Before submitting a pull request:

- Check the codebase to ensure that your feature doesn't already exist.
- Check the pull requests to ensure that another person hasn't already submitted the feature or fix.

## Development setup

```bash
git clone https://github.com/mauriziofonte/sparsedigest.git
cd sparsedigest
composer install
```

Useful commands:

| Command                 | What it does                                          |
|-------------------------|-------------------------------------------------------|
| `composer test`         | Run the PHPUnit test suite                            |
| `composer analyse`      | Run PHPStan (level `max`) on `src/` and `tests/`      |
| `composer cs:check`     | Check coding style without modifying files            |
| `composer cs:fix`       | Fix coding style in place                             |
| `composer check`        | All of the above, as run in CI                        |
| `composer bench`        | Run the PHPBench benchmark suite                      |

## Requirements

- **Coding style**: [PSR-12](https://www.php-fig.org/psr/psr-12/), enforced by [PHP CS Fixer](https://cs.symfony.com/) through `.php-cs-fixer.dist.php`. Run `composer cs:fix` before committing.
- **Static analysis**: `composer analyse` must report no errors. Do not add baseline entries or `@phpstan-ignore` comments without explaining why.
- **PHP compatibility**: the library supports PHP 7.2 and later. Do not use syntax or functions introduced after PHP 7.2 in `src/` (no typed properties, union types, `match`, named arguments, `str_contains()`, ...).
- **Add tests!** Your patch won't be accepted if it doesn't have tests.
- **Hash stability**: a change that alters the hash produced for an existing file is a breaking change, since users persist hashes. Call it out explicitly in the pull request.
- **Document any change in behaviour**: keep `README.md` up to date and add an entry to `CHANGELOG.md` under "Unreleased".
- **One pull request per feature**: if you want to do more than one thing, send multiple pull requests.
- **Send coherent history**: make sure each individual commit in your pull request is meaningful. If you had to make multiple intermediate commits while developing, please [squash them](https://git-scm.com/book/en/v2/Git-Tools-Rewriting-History#_squashing) before submitting.

## Security

Please do not report security vulnerabilities through public issues: see [SECURITY.md](SECURITY.md).

**Happy coding**!
