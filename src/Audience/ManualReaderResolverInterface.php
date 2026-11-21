<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Audience;

/**
 * 1.13 port: the reader of a manual root for the current user — null when the user may not open that root at all.
 * The default is {@see ManualReaderResolver} (the root's role, then one `isGranted()` per audience of the root); alias
 * this interface to your own service to decide audiences another way. An implementation must only return audiences
 * of the given root, and must resolve them per call (no memo across requests: FrankenPHP worker mode).
 */
interface ManualReaderResolverInterface
{
    /**
     * The reader of this root, or null when the current user may not open it.
     */
    public function reader(ManualRoot $root): ?ManualReader;
}
