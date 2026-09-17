<?php

declare(strict_types=1);

use Laravel\Mcp\Enums\ErrorCode;
use Laravel\Mcp\Enums\MetaKey;
use Laravel\Mcp\Enums\ProtocolVersion;
use Laravel\Mcp\Enums\RequestHeader;

it('answers the MCP v1 discovery handshake over the registered browser route', function () {
    $payload = feature_ai_bridge_mcp_v1_discovery_payload();

    $this->postJson('/mcp/browser', $payload, [
        RequestHeader::PROTOCOL_VERSION->value => ProtocolVersion::LATEST->value,
        RequestHeader::METHOD->value => 'server/discover',
    ])->assertOk()
        ->assertJsonPath('jsonrpc', '2.0')
        ->assertJsonPath('id', 'mcp-v1-discovery')
        ->assertJsonPath('result.supportedVersions.0', ProtocolVersion::LATEST->value)
        ->assertJsonPath('result.capabilities.tools.listChanged', false);
});

it('rejects an MCP v1 discovery request whose required transport headers are absent', function () {
    $this->postJson('/mcp/browser', feature_ai_bridge_mcp_v1_discovery_payload())
        ->assertStatus(400)
        ->assertJsonPath('id', 'mcp-v1-discovery')
        ->assertJsonPath('error.code', ErrorCode::HEADER_MISMATCH->value)
        ->assertJsonPath(
            'error.message',
            'Header mismatch: The [MCP-Protocol-Version] header is required.',
        );
});

/**
 * @return array<string, mixed>
 */
function feature_ai_bridge_mcp_v1_discovery_payload(): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 'mcp-v1-discovery',
        'method' => 'server/discover',
        'params' => [
            '_meta' => [
                MetaKey::PROTOCOL_VERSION->value => ProtocolVersion::LATEST->value,
                MetaKey::CLIENT_CAPABILITIES->value => [],
                MetaKey::CLIENT_INFO->value => [
                    'name' => 'ai-bridge-test-client',
                    'version' => '1.0.0',
                ],
            ],
        ],
    ];
}
