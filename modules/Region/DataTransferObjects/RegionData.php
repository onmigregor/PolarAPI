<?php

namespace Modules\Region\DataTransferObjects;

readonly class RegionData
{
    public function __construct(
        public string $citCode,
        public string $citName,
        public string $staCode,
    ) {}

    public static function fromRequest($request): self
    {
        return new self(
            citCode: $request->validated('citCode'),
            citName: $request->validated('citName'),
            staCode: $request->validated('staCode'),
        );
    }
}
