<?php declare(strict_types = 1);

// osfsl-P:/moeller-lars-implementation/vendor/composer/../laravel/framework/src/Illuminate/Foundation/Http/Middleware/PreventRequestForgery.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Illuminate\Foundation\Http\Middleware\PreventRequestForgery
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-d636885af0f2c06d244a2dfb9ae754af89694c8e9f099ca94946cf5e37bbf04d-8.3.33-6.70.0.3',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'filename' => 'P:/moeller-lars-implementation/vendor/composer/../laravel/framework/src/Illuminate/Foundation/Http/Middleware/PreventRequestForgery.php',
      ),
    ),
    'namespace' => 'Illuminate\\Foundation\\Http\\Middleware',
    'name' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
    'shortName' => 'PreventRequestForgery',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 19,
    'endLine' => 321,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
      0 => 'Illuminate\\Foundation\\Http\\Middleware\\Concerns\\ExcludesPaths',
      1 => 'Illuminate\\Support\\InteractsWithTime',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'app' => 
      array (
        'declaringClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'implementingClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'name' => 'app',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The application instance.
 *
 * @var \\Illuminate\\Contracts\\Foundation\\Application
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 29,
        'endLine' => 29,
        'startColumn' => 5,
        'endColumn' => 19,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'encrypter' => 
      array (
        'declaringClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'implementingClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'name' => 'encrypter',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The encrypter implementation.
 *
 * @var \\Illuminate\\Contracts\\Encryption\\Encrypter
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 36,
        'endLine' => 36,
        'startColumn' => 5,
        'endColumn' => 25,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'except' => 
      array (
        'declaringClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'implementingClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'name' => 'except',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 43,
            'endLine' => 43,
            'startTokenPos' => 108,
            'startFilePos' => 1117,
            'endTokenPos' => 109,
            'endFilePos' => 1118,
          ),
        ),
        'docComment' => '/**
 * The URIs that should be excluded.
 *
 * @var array<int, string>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 43,
        'endLine' => 43,
        'startColumn' => 5,
        'endColumn' => 27,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'neverVerify' => 
      array (
        'declaringClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'implementingClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'name' => 'neverVerify',
        'modifiers' => 18,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 50,
            'endLine' => 50,
            'startTokenPos' => 122,
            'startFilePos' => 1280,
            'endTokenPos' => 123,
            'endFilePos' => 1281,
          ),
        ),
        'docComment' => '/**
 * The globally ignored URIs that should be excluded from CSRF verification.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 50,
        'endLine' => 50,
        'startColumn' => 5,
        'endColumn' => 39,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'addHttpCookie' => 
      array (
        'declaringClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'implementingClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'name' => 'addHttpCookie',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'true',
          'attributes' => 
          array (
            'startLine' => 57,
            'endLine' => 57,
            'startTokenPos' => 134,
            'startFilePos' => 1434,
            'endTokenPos' => 134,
            'endFilePos' => 1437,
          ),
        ),
        'docComment' => '/**
 * Indicates whether the XSRF-TOKEN cookie should be set on the response.
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 57,
        'endLine' => 57,
        'startColumn' => 5,
        'endColumn' => 36,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'allowSameSite' => 
      array (
        'declaringClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'implementingClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'name' => 'allowSameSite',
        'modifiers' => 18,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 64,
            'endLine' => 64,
            'startTokenPos' => 147,
            'startFilePos' => 1591,
            'endTokenPos' => 147,
            'endFilePos' => 1595,
          ),
        ),
        'docComment' => '/**
 * Indicates whether requests from the same site should be allowed.
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 64,
        'endLine' => 64,
        'startColumn' => 5,
        'endColumn' => 44,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'originOnly' => 
      array (
        'declaringClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'implementingClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'name' => 'originOnly',
        'modifiers' => 18,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 71,
            'endLine' => 71,
            'startTokenPos' => 160,
            'startFilePos' => 1740,
            'endTokenPos' => 160,
            'endFilePos' => 1744,
          ),
        ),
        'docComment' => '/**
 * Indicates whether only origin verification should be used.
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 71,
        'endLine' => 71,
        'startColumn' => 5,
        'endColumn' => 41,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
    ),
    'immediateMethods' => 
    array (
      '__construct' => 
      array (
        'name' => '__construct',
        'parameters' => 
        array (
          'app' => 
          array (
            'name' => 'app',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Contracts\\Foundation\\Application',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 79,
            'endLine' => 79,
            'startColumn' => 33,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'encrypter' => 
          array (
            'name' => 'encrypter',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Contracts\\Encryption\\Encrypter',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 79,
            'endLine' => 79,
            'startColumn' => 51,
            'endColumn' => 70,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create a new middleware instance.
 *
 * @param  \\Illuminate\\Contracts\\Foundation\\Application  $app
 * @param  \\Illuminate\\Contracts\\Encryption\\Encrypter  $encrypter
 */',
        'startLine' => 79,
        'endLine' => 83,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Foundation\\Http\\Middleware',
        'declaringClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'implementingClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'currentClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'aliasName' => NULL,
      ),
      'handle' => 
      array (
        'name' => 'handle',
        'parameters' => 
        array (
          'request' => 
          array (
            'name' => 'request',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 95,
            'endLine' => 95,
            'startColumn' => 28,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'next' => 
          array (
            'name' => 'next',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Closure',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 95,
            'endLine' => 95,
            'startColumn' => 38,
            'endColumn' => 50,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Handle an incoming request.
 *
 * @param  \\Illuminate\\Http\\Request  $request
 * @param  \\Closure  $next
 * @return mixed
 *
 * @throws \\Illuminate\\Session\\TokenMismatchException
 * @throws \\Illuminate\\Http\\Exceptions\\OriginMismatchException
 */',
        'startLine' => 95,
        'endLine' => 112,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Foundation\\Http\\Middleware',
        'declaringClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'implementingClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'currentClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'aliasName' => NULL,
      ),
      'isReading' => 
      array (
        'name' => 'isReading',
        'parameters' => 
        array (
          'request' => 
          array (
            'name' => 'request',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 120,
            'endLine' => 120,
            'startColumn' => 34,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the HTTP request uses a ‘read’ verb.
 *
 * @param  \\Illuminate\\Http\\Request  $request
 * @return bool
 */',
        'startLine' => 120,
        'endLine' => 123,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Foundation\\Http\\Middleware',
        'declaringClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'implementingClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'currentClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'aliasName' => NULL,
      ),
      'runningUnitTests' => 
      array (
        'name' => 'runningUnitTests',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the application is running unit tests.
 *
 * @return bool
 */',
        'startLine' => 130,
        'endLine' => 133,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Foundation\\Http\\Middleware',
        'declaringClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'implementingClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'currentClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'aliasName' => NULL,
      ),
      'hasValidOrigin' => 
      array (
        'name' => 'hasValidOrigin',
        'parameters' => 
        array (
          'request' => 
          array (
            'name' => 'request',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 143,
            'endLine' => 143,
            'startColumn' => 39,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the request has a valid origin based on the Sec-Fetch-Site header.
 *
 * @param  \\Illuminate\\Http\\Request  $request
 * @return bool
 *
 * @throws \\Illuminate\\Http\\Exceptions\\OriginMismatchException
 */',
        'startLine' => 143,
        'endLine' => 160,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Foundation\\Http\\Middleware',
        'declaringClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'implementingClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'currentClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'aliasName' => NULL,
      ),
      'tokensMatch' => 
      array (
        'name' => 'tokensMatch',
        'parameters' => 
        array (
          'request' => 
          array (
            'name' => 'request',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 168,
            'endLine' => 168,
            'startColumn' => 36,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the session and input CSRF tokens match.
 *
 * @param  \\Illuminate\\Http\\Request  $request
 * @return bool
 */',
        'startLine' => 168,
        'endLine' => 175,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Foundation\\Http\\Middleware',
        'declaringClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'implementingClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'currentClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'aliasName' => NULL,
      ),
      'getTokenFromRequest' => 
      array (
        'name' => 'getTokenFromRequest',
        'parameters' => 
        array (
          'request' => 
          array (
            'name' => 'request',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 183,
            'endLine' => 183,
            'startColumn' => 44,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the CSRF token from the request.
 *
 * @param  \\Illuminate\\Http\\Request  $request
 * @return string|null
 */',
        'startLine' => 183,
        'endLine' => 196,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Foundation\\Http\\Middleware',
        'declaringClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'implementingClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'currentClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'aliasName' => NULL,
      ),
      'shouldAddXsrfTokenCookie' => 
      array (
        'name' => 'shouldAddXsrfTokenCookie',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the cookie should be added to the response.
 *
 * @return bool
 */',
        'startLine' => 203,
        'endLine' => 210,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Foundation\\Http\\Middleware',
        'declaringClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'implementingClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'currentClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'aliasName' => NULL,
      ),
      'addCookieToResponse' => 
      array (
        'name' => 'addCookieToResponse',
        'parameters' => 
        array (
          'request' => 
          array (
            'name' => 'request',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 219,
            'endLine' => 219,
            'startColumn' => 44,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'response' => 
          array (
            'name' => 'response',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 219,
            'endLine' => 219,
            'startColumn' => 54,
            'endColumn' => 62,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add the CSRF token to the response cookies.
 *
 * @param  \\Illuminate\\Http\\Request  $request
 * @param  \\Symfony\\Component\\HttpFoundation\\Response  $response
 * @return \\Symfony\\Component\\HttpFoundation\\Response
 */',
        'startLine' => 219,
        'endLine' => 230,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Foundation\\Http\\Middleware',
        'declaringClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'implementingClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'currentClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'aliasName' => NULL,
      ),
      'newCookie' => 
      array (
        'name' => 'newCookie',
        'parameters' => 
        array (
          'request' => 
          array (
            'name' => 'request',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 239,
            'endLine' => 239,
            'startColumn' => 34,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'config' => 
          array (
            'name' => 'config',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 239,
            'endLine' => 239,
            'startColumn' => 44,
            'endColumn' => 50,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create a new "XSRF-TOKEN" cookie that contains the CSRF token.
 *
 * @param  \\Illuminate\\Http\\Request  $request
 * @param  array  $config
 * @return \\Symfony\\Component\\HttpFoundation\\Cookie
 */',
        'startLine' => 239,
        'endLine' => 253,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Foundation\\Http\\Middleware',
        'declaringClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'implementingClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'currentClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'aliasName' => NULL,
      ),
      'except' => 
      array (
        'name' => 'except',
        'parameters' => 
        array (
          'uris' => 
          array (
            'name' => 'uris',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 261,
            'endLine' => 261,
            'startColumn' => 35,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Indicate that the given URIs should be excluded from CSRF verification.
 *
 * @param  array|string  $uris
 * @return void
 */',
        'startLine' => 261,
        'endLine' => 266,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Foundation\\Http\\Middleware',
        'declaringClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'implementingClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'currentClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'aliasName' => NULL,
      ),
      'allowSameSite' => 
      array (
        'name' => 'allowSameSite',
        'parameters' => 
        array (
          'allow' => 
          array (
            'name' => 'allow',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 274,
                'endLine' => 274,
                'startTokenPos' => 969,
                'startFilePos' => 7338,
                'endTokenPos' => 969,
                'endFilePos' => 7341,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 274,
            'endLine' => 274,
            'startColumn' => 42,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Indicate that requests from the same site should be allowed.
 *
 * @param  bool  $allow
 * @return void
 */',
        'startLine' => 274,
        'endLine' => 277,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Foundation\\Http\\Middleware',
        'declaringClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'implementingClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'currentClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'aliasName' => NULL,
      ),
      'useOriginOnly' => 
      array (
        'name' => 'useOriginOnly',
        'parameters' => 
        array (
          'originOnly' => 
          array (
            'name' => 'originOnly',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 285,
                'endLine' => 285,
                'startTokenPos' => 999,
                'startFilePos' => 7591,
                'endTokenPos' => 999,
                'endFilePos' => 7594,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 285,
            'endLine' => 285,
            'startColumn' => 42,
            'endColumn' => 59,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Indicate that only origin verification should be used.
 *
 * @param  bool  $originOnly
 * @return void
 */',
        'startLine' => 285,
        'endLine' => 288,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Foundation\\Http\\Middleware',
        'declaringClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'implementingClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'currentClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'aliasName' => NULL,
      ),
      'getExcludedPaths' => 
      array (
        'name' => 'getExcludedPaths',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the URIs that should be excluded.
 *
 * @return array
 */',
        'startLine' => 295,
        'endLine' => 298,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Foundation\\Http\\Middleware',
        'declaringClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'implementingClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'currentClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'aliasName' => NULL,
      ),
      'serialized' => 
      array (
        'name' => 'serialized',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the cookie contents should be serialized.
 *
 * @return bool
 */',
        'startLine' => 305,
        'endLine' => 308,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Foundation\\Http\\Middleware',
        'declaringClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'implementingClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'currentClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'aliasName' => NULL,
      ),
      'flushState' => 
      array (
        'name' => 'flushState',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Flush the state of the middleware.
 *
 * @return void
 */',
        'startLine' => 315,
        'endLine' => 320,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Foundation\\Http\\Middleware',
        'declaringClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'implementingClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'currentClassName' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        'aliasName' => NULL,
      ),
    ),
    'traitsData' => 
    array (
      'aliases' => 
      array (
      ),
      'modifiers' => 
      array (
      ),
      'precedences' => 
      array (
      ),
      'hashes' => 
      array (
      ),
    ),
  ),
));