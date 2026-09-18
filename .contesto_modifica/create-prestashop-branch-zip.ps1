param(
    [string]$BaseBranch = "develop"
)

$ErrorActionPreference = "Stop"

$repoRoot = (Get-Location).Path
$contextDir = Join-Path $repoRoot ".contesto_modifica"
$uploadDir = Join-Path $contextDir "chat-upload"
$tempDir = Join-Path $repoRoot "branch-files-for-chat"

$branchZipName = "prestashop-42814-current.zip"
$branchZip = Join-Path $uploadDir $branchZipName
$patchName = "branch-diff-current.patch"
$patch = Join-Path $uploadDir $patchName

Write-Host ""
Write-Host "PrestaShop branch export for ChatGPT" -ForegroundColor Cyan
Write-Host "Repository: $repoRoot"
Write-Host "Base branch: $BaseBranch"
Write-Host ""

# Verify that we are inside a Git repository.
git rev-parse --is-inside-work-tree *> $null
if ($LASTEXITCODE -ne 0) {
    throw "The current directory is not a Git repository. Run this script from the PrestaShop repository root."
}

# Verify/create .contesto_modifica.
if (-not (Test-Path -LiteralPath $contextDir)) {
    New-Item -ItemType Directory -Path $contextDir | Out-Null
}

# Recreate the upload directory so it always contains only the current export.
if (Test-Path -LiteralPath $uploadDir) {
    Remove-Item -LiteralPath $uploadDir -Recurse -Force
}
New-Item -ItemType Directory -Path $uploadDir | Out-Null

# Clean temporary branch-copy directory.
if (Test-Path -LiteralPath $tempDir) {
    Remove-Item -LiteralPath $tempDir -Recurse -Force
}
New-Item -ItemType Directory -Path $tempDir | Out-Null

# Collect:
# - committed changes in the branch vs base
# - unstaged changes
# - staged changes
# - untracked files
#
# Exclude generated chat-upload content to avoid recursively including exports.
$files = @(
    git diff --name-only "$BaseBranch...HEAD"
    git diff --name-only
    git diff --name-only --cached
    git ls-files --others --exclude-standard
) |
    Where-Object {
        $_ -and
        $_.Trim() -ne "" -and
        -not $_.Trim().StartsWith(".contesto_modifica/chat-upload/") -and
        -not $_.Trim().StartsWith(".contesto_modifica\chat-upload\")
    } |
    ForEach-Object { $_.Trim() } |
    Sort-Object -Unique

if (-not $files -or $files.Count -eq 0) {
    Write-Host "No modified, staged, committed, or untracked files were found." -ForegroundColor Yellow
} else {
    Write-Host "Files found:" -ForegroundColor Cyan
}

$missingFiles = @()

foreach ($file in $files) {
    Write-Host " - $file"

    $source = Join-Path $repoRoot $file

    # Deleted/renamed files cannot be copied into the ZIP.
    if (-not (Test-Path -LiteralPath $source)) {
        $missingFiles += $file
        Write-Host "   SKIP: file does not exist in the working tree (probably deleted or renamed)." -ForegroundColor Yellow
        continue
    }

    $target = Join-Path $tempDir $file
    $targetDir = Split-Path -Path $target -Parent

    if (-not (Test-Path -LiteralPath $targetDir)) {
        New-Item -ItemType Directory -Force -Path $targetDir | Out-Null
    }

    Copy-Item -LiteralPath $source -Destination $target -Force
}

# Create branch ZIP if there are existing files to export.
$copiedFiles = @(Get-ChildItem -LiteralPath $tempDir -Recurse -File)

if ($copiedFiles.Count -gt 0) {
    Compress-Archive `
        -Path (Join-Path $tempDir "*") `
        -DestinationPath $branchZip `
        -Force

    Write-Host ""
    Write-Host "Branch ZIP created:" -ForegroundColor Green
    Write-Host $branchZip
} else {
    Write-Host ""
    Write-Host "No existing files could be copied into the branch ZIP." -ForegroundColor Yellow
}

# If deleted/renamed files are present, also create a patch so deletion/rename
# information is not lost.
if ($missingFiles.Count -gt 0) {
    git diff "$BaseBranch...HEAD" | Set-Content -LiteralPath $patch -Encoding utf8

    Write-Host ""
    Write-Host "Deleted or renamed files detected." -ForegroundColor Yellow
    Write-Host "Patch created:" -ForegroundColor Green
    Write-Host $patch
}

# Copy the documentation files that provide context to the next chat.
$contextFiles = @(
    "CONTEXT-HANDOFF.md",
    "descrizione.md",
    "step-pr-crud-logging.md"
)

foreach ($contextFile in $contextFiles) {
    $source = Join-Path $contextDir $contextFile

    if (Test-Path -LiteralPath $source) {
        Copy-Item -LiteralPath $source -Destination (Join-Path $uploadDir $contextFile) -Force
    } else {
        Write-Host "WARNING: context file not found: $source" -ForegroundColor Yellow
    }
}

# Remove temporary directory.
Remove-Item -LiteralPath $tempDir -Recurse -Force

Write-Host ""
Write-Host "Export completed." -ForegroundColor Green
Write-Host "Files ready for upload are in:" -ForegroundColor Cyan
Write-Host $uploadDir
Write-Host ""

$readyFiles = @(Get-ChildItem -LiteralPath $uploadDir -File | Sort-Object Name)

foreach ($readyFile in $readyFiles) {
    Write-Host " - $($readyFile.Name)"
}

Write-Host ""
Write-Host "Upload the files contained in .contesto_modifica\chat-upload to ChatGPT." -ForegroundColor Green
Write-Host ""
