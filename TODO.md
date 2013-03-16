not so urgent:
- mod_rewrite + _url() function rules. correct
- mod_rewrite leverage & bulletproof & test
- examine & inspect other micro-frameworks for inspiration about features & architecture
- error handling & logging should be greatly leveraged! currently it may be buggy!!
- input filtering helper mechanism needed? Extra module can be added for this.
    - form model was good in django. but it requires models, and active record i guess.
    - study filter_var and siblings
- admin panel is missing! Extra module?
- routing system is missing! Extra module?
- i18n & l10n. Extra module.
    - mustache -> i18n?
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
