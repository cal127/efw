not so urgent:
- integrate a templating engine (mustache, twig)
- integrate an ORM(doctrine, redbean, paris) or a fluent query builder (fluent pdo, fluent query builder, idiorm)
    - Consider refactoring and adding DBObj as a module.
- mod rewrite & pretty urls support!
- examine & inspect other micro-frameworks for inspiration about features & architecture
- Tpl: auto_escape should also apply to iterable objects (recursively)!
- new utils module to include small native & 3rd party & libs?
- error handling & logging should be greatly leveraged! currently it may be buggy!!
- input filtering helper mechanism needed? Extra module can be added for this.
    - form model was good in django. but it requires models, and active record i guess.
- admin panel is missing! Extra module?
- routing system is missing! Extra module?
- consider autoloading to load controllers and libs
- i18n & l10n. Extra module.
- hook for changing the public directory name.

elements to be considered on framework design
=============================================

- startup
    - config
    - libraries
- front controller
    - router
- model
    - abstraction layer (PDO)
    - sql injection
    - orm?
- view
    - output escaping
    - templating engine?
- controller
    - input validating
    - access control
        - user system
- user system
- other
    - i18n & l10n
    - admin panel
    - tests
