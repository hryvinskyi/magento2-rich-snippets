<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Variant;

use Magento\Framework\App\RequestInterface;

/**
 * The address that selects one variant of a configurable product: the product's canonical address with one query
 * parameter per configurable attribute, named by the attribute's code, valued by the option's ID
 * (`…/weekender.html?color=49&size=167`)
 *
 * The product page reads the same parameters back to preselect the variant, so the address a snippet gives for a
 * variant opens the page with that variant chosen.
 */
class VariantAddress
{
    /**
     * The address of a variant
     *
     * @param string $productUrl the configurable product's canonical address
     * @param array<string,int> $options option ID by attribute code, in the product's attribute order
     * @return string
     */
    public function url(string $productUrl, array $options): string
    {
        if ($options === []) {
            return $productUrl;
        }

        return $productUrl . (str_contains($productUrl, '?') ? '&' : '?') . http_build_query($options);
    }

    /**
     * The options a request's parameters name for the attributes, when it names one for each
     *
     * @param RequestInterface $request
     * @param list<string> $codes the configurable attributes' codes
     * @return array<string, int>|null option ID by attribute code; null when an attribute has no valid parameter
     */
    public function requested(RequestInterface $request, array $codes): ?array
    {
        if ($codes === []) {
            return null;
        }
        $options = [];
        foreach ($codes as $code) {
            $value = $request->getParam($code);
            if (!is_string($value) || !ctype_digit($value) || (int) $value === 0) {
                return null;
            }
            $options[$code] = (int) $value;
        }

        return $options;
    }
}
