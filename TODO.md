- error handling & logging should be greatly leveraged! currently it may
  be buggy!!
- mod rewrite support!
- user system is missing!
- admin panel is missing!
- routing system is missing!
- consider autoloading to load controller files
- dynamic library loading feature is missing!

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
