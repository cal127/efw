urgent:
- new utils module to include small native & 3rd party & libs.

not so urgent:
- Tpl: auto_escape should also apply to iterable objects (recursively)!
- $param binding is not working properly when $param includes /!!!
- error handling & logging should be greatly leveraged! currently it may
  be buggy!!
- input filtering helper mechanism needed? Extra module can be added for this.
- mod rewrite & pretty urls support!
- admin panel is missing! Extra module?
- routing system is missing! Extra module?
- consider autoloading to load controllers and libs
- i18n & l10n. Extra module.
- models & orm missing! Consider refactoring and adding DBObj as a module.
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
