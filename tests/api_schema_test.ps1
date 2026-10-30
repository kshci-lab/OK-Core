$ErrorActionPreference = 'Stop'
$schema = Join-Path $PSScriptRoot '../api/v1/schemas/kf-put.schema.json'
$example = Get-Content -LiteralPath (Join-Path $PSScriptRoot '../api/v1/examples/kf-put.json') -Raw
$cases = @(
    @{ Name='existing payload'; Valid=$true; Change={} },
    @{ Name='string revision'; Valid=$true; Change={ param($p) $p.source_revision='9223372036854775807' } },
    @{ Name='empty stages'; Valid=$true; Change={ param($p) $p.stages=@() } },
    @{ Name='revision overflow'; Valid=$false; Change={ param($p) $p.source_revision='9223372036854775808' } },
    @{ Name='group overflow'; Valid=$false; Change={ param($p) $p.share_group_ids=@('2147483648') } },
    @{ Name='summary overflow'; Valid=$false; Change={ param($p) $p.summary=('x'*256) } },
    @{ Name='missing activity'; Valid=$false; Change={ param($p) $p.Remove('activity') } },
    @{ Name='unknown stage'; Valid=$false; Change={ param($p) $p.stages[0].stage_code='stage4' } },
    @{ Name='stage not text'; Valid=$false; Change={ param($p) $p.stages[0].rendered_content=@('bad') } },
    @{ Name='slot missing value'; Valid=$false; Change={ param($p) $p.stages[0].slots[0].Remove('value') } },
    @{ Name='snapshot not object'; Valid=$false; Change={ param($p) $p.context_package.items[0].snapshot=@('bad') } },
    @{ Name='legacy empty snapshot'; Valid=$true; Change={ param($p) $p.context_package.items[0].snapshot=@() } }
)
foreach ($case in $cases) {
    $payload = ConvertFrom-Json -InputObject $example -AsHashtable
    $null = & $case.Change $payload
    $json = ConvertTo-Json -InputObject $payload -Depth 30
    $valid = Test-Json -Json $json -SchemaFile $schema -ErrorAction SilentlyContinue
    if ($valid -ne $case.Valid) { throw ('Schema case failed: '+$case.Name) }
}
Write-Output ('PASS: '+$cases.Count+' JSON Schema examples and boundary cases.')
