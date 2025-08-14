Yaml
==============

Loads and dumps YAML files.

### Features:

- [x] Comment and empty line preservation
- [x] Manual construction via code
- [ ] Multi-line values

### Examples:

From file (contents are the same as the string in the next example):

```php
use Splxnter\Yaml\Dumper;
use Splxnter\Yaml\Yaml;

$parser = Yaml::parseFile(__DIR__ . '/services.yaml');

echo Yaml::dump($parser->getTokens(), Dumper::DUMP_EMPTY_LINES | Dumper::DUMP_COMMENTS);
```

From string:

```php
use Splxnter\Yaml\Dumper;
use Splxnter\Yaml\Yaml;

$contents = <<<YAML
services:
  # Router
  router:
    class: App\Routing\Router # another comment

  # Something
  something:
    class: stdClass
YAML;

$parser = Yaml::parse($contents);

echo Yaml::dump($parser->getTokens(), Dumper::DUMP_EMPTY_LINES | Dumper::DUMP_COMMENTS);
```

Via code:

```php
use Splxnter\Yaml\Dumper;
use Splxnter\Yaml\Token;
use Splxnter\Yaml\Yaml;

$tokens = [
    Token::new(name: 'services'),
    Token::new(2, comment: 'Router'),
    Token::new(2, name: 'router'),
    Token::new(4, name: 'class', value: 'App\Routing\Router', comment: 'another comment'),
    Token::new(),
    Token::new(2, comment: 'Something'),
    Token::new(2, name: 'something'),
    Token::new(4, name: 'class', value: 'stdClass', comment: 'another comment'),
];

echo Yaml::dump($tokens, Dumper::DUMP_EMPTY_LINES | Dumper::DUMP_COMMENTS);
```

In all examples output is the following:

```yaml
services:
  # Router
  router:
    class: 'App\Routing\Router' # another comment

  # Something
  something:
    class: stdClass # another commen
```
