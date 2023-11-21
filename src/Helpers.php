<?php

declare(strict_types=1);
/**
 * This file is part of the EasySDK package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Larva\EasySDK;

use Larva\EasySDK\Contracts\Arrayable;
use Larva\EasySDK\Exceptions\RuntimeException;
use Larva\EasySDK\Support\Arr;
use Larva\EasySDK\Support\Collection;

function data_get($data, $key, $default = null)
{
    return match (true) {
        is_array($data) => Arr::get($data, $key, $default),
        $data instanceof Collection => $data->get($key, $default),
        $data instanceof Arrayable => Arr::get($data->toArray(), $key, $default),
        $data instanceof \ArrayIterator => $data->getArrayCopy()[$key] ?? $default,
        $data instanceof \ArrayAccess => $data[$key] ?? $default,
        $data instanceof \IteratorAggregate && $data->getIterator() instanceof \ArrayIterator => $data->getIterator()->getArrayCopy()[$key] ?? $default,
        is_object($data) => $data->{$key} ?? $default,
        default => throw new RuntimeException(sprintf('Can\'t access data with key "%s"', $key)),
    };
}

function data_to_array($data): array
{
    return match (true) {
        is_array($data) => $data,
        $data instanceof Collection => $data->all(),
        $data instanceof Arrayable => $data->toArray(),
        $data instanceof \IteratorAggregate && $data->getIterator() instanceof \ArrayIterator => $data->getIterator()->getArrayCopy(),
        $data instanceof \ArrayIterator => $data->getArrayCopy(),
        default => throw new RuntimeException(sprintf('Can\'t transform data to array')),
    };
}
