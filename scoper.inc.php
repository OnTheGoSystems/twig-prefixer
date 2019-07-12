<?php

declare(strict_types=1);

namespace OTGS\Toolset\TwigPrefixer {

	const TWIG_BASE_DIR = __DIR__ . '/vendor/twig/twig';

	function endsWith( $haystack, $needle ) {
		$length = strlen( $needle );
		if ( $length == 0 ) {
			return true;
		}

		return ( substr( $haystack, - $length ) === $needle );
	}

}

namespace {

	use Isolated\Symfony\Component\Finder\Finder;
	use const OTGS\Toolset\TwigPrefixer\TWIG_BASE_DIR;

	return [

		// For more see: https://github.com/humbug/php-scoper#finders-and-paths
		'finders' => [
			Finder::create()->files()->in( TWIG_BASE_DIR . '/lib' ),
			Finder::create()->files()->in( TWIG_BASE_DIR . '/src' ),
		],

		// When scoping PHP files, there will be scenarios where some of the code being scoped indirectly references the
		// original namespace. These will include, for example, strings or string manipulations. PHP-Scoper has limited
		// support for prefixing such strings. To circumvent that, you can define patchers to manipulate the file to your
		// heart contents.
		//
		// For more see: https://github.com/humbug/php-scoper#patchers
		'patchers' => [

			/**
			 * Patcher for all files.
			 */
			function ( string $filePath, string $prefix, string $contents ): string {
				// Hardcoded class names in code
				$contents = preg_replace(
					'/("|\')((\\\\){1,2}Twig(\\\\){1,2}[A-Za-z\\\\]+)\1/m',
					'$1\\\\\\\\OTGS\\\\\\\\Toolset$2$1',
					$contents
				);

				// Hardcoded "use" statements
				$contents = preg_replace(
					'/use\s+(Twig)(\\\\){1,2}/m',
					'use \\\\\\\\OTGS\\\\\\\\Toolset\\\\\\\\Twig\\\\\\\\',
					$contents
				);

				// Add namespaces to generated Twig template names
				$contents = preg_replace(
					'/(\'|")(__TwigTemplate_)\1/m',
					'$1\\\\\\\\OTGS\\\\\\\\Toolset\\\\\\\\$2$1',
					$contents
				);

				return $contents;
			},

			/**
			 * Patcher for \OTGS\Toolset\Twig\Node\ModuleNode.
			 */
			function ( string $filePath, string $prefix, string $contents ): string {
				if ( \OTGS\Toolset\TwigPrefixer\endsWith( $filePath, 'src/Node/ModuleNode.php' ) ) {
					// Fix template compilation - add the namespace to the template file.
					$contents = preg_replace(
						'/(compileClassHeader\s*\([^\)]+\)\s*{\s*\s*\$compiler\s*->\s*write\s*\(\s*)"\\\\n\\\\n"(\s*\)\s*;)/m',
						'$1"\\n\\nnamespace OTGS\\\\Toolset;\\n\\n"$2',
						$contents
					);

					// When generating the PHP template, make sure its class declaration doesn't contain the namespace.
					// That's the only place where we don't want to have it.
					$string_to_remove = '\\OTGS\\Toolset\\';
					$contents = preg_replace(
						'/(->write\s*\(\s*\'class \'\s*\.\s*)(\$compiler\s*->\s*getEnvironment\s*\(\s*\)\s*->\s*getTemplateClass\s*\(\s*\$this\s*->\s*getSourceContext\s*\(\s*\)\s*->\s*getName\s*\(\s*\)\s*,\s*\$this\s*->\s*getAttribute\s*\(\s*\'index\'\s*\)\s*\))/m',
						'$1 \\substr( $2, ' . strlen( $string_to_remove ) . ' ) ',
						$contents
					);
				}

				return $contents;
			},

			/**
			 * Patcher for \OTGS\Toolset\Twig\Extension\CoreExtension.
			 */
			function ( string $filePath, string $prefix, string $contents ): string {
				// Fix the usage of global twig_* and _twig_* functions.
				if ( \OTGS\Toolset\TwigPrefixer\endsWith( $filePath, 'src/Extension/CoreExtension.php' ) ) {
					$contents = preg_replace(
						'/(new \\\\OTGS\\\\Toolset\\\\Twig\\\\TwigFilter\(\s*\'[^\']+\'\s*,\s*\')((_)?twig_[^\']+\')/m',
						'$1\\\\\\\\OTGS\\\\\\\\Toolset\\\\\\\\$2',
						$contents
					);

					// Also handle the occurrence in the is_safe_callback array element.
					$contents = preg_replace(
						'/(new \\\\OTGS\\\\Toolset\\\\Twig\\\\TwigFilter\(\s*\'[^\']+\'\s*,\s*\'.*twig_[^\']+\',\s*\[[^\]]*,\s*\'is_safe_callback\'\s*=>\s*\')((_)?twig_[^\']+\'\s*\]\s*\))/m',
						'$1\\\\\\\\OTGS\\\\\\\\Toolset\\\\\\\\$2',
						$contents
					);

				}

				return $contents;
			},
		],
	];

}
