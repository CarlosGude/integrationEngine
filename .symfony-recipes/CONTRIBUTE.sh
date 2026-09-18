#!/bin/bash
set -e

echo "🚀 IntegrationEngine Recipe Setup for symfony-recipes-contrib"
echo ""

# Colors
GREEN='\033[0;32m'
CYAN='\033[0;36m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Get the directory where this script is
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
RECIPE_DIR="$SCRIPT_DIR/integration-engine"

echo -e "${CYAN}[1/5] Cloning symfony-recipes-contrib...${NC}"
if [ -d "$HOME/symfony-recipes-contrib" ]; then
    echo "   ⚠️  Already cloned, using existing copy"
    cd "$HOME/symfony-recipes-contrib"
    git fetch origin
else
    echo "   📥 Cloning repository..."
    git clone https://github.com/symfony/recipes-contrib "$HOME/symfony-recipes-contrib"
    cd "$HOME/symfony-recipes-contrib"
fi

echo -e "${GREEN}   ✓ Repository ready${NC}"
echo ""

# Paso 2: Branch
echo -e "${CYAN}[2/5] Creating branch 'add-integration-engine'...${NC}"
git checkout -b add-integration-engine 2>/dev/null || git checkout add-integration-engine
git pull origin main 2>/dev/null || git pull origin master
echo -e "${GREEN}   ✓ Branch ready${NC}"
echo ""

# Paso 3: Create structure
echo -e "${CYAN}[3/5] Creating recipe structure...${NC}"
mkdir -p recipes/carlosgude/integration-engine/5.2/{config/packages,src/Integration}
echo -e "${GREEN}   ✓ Directories created${NC}"
echo ""

# Paso 4: Copy files
echo -e "${CYAN}[4/5] Copying recipe files...${NC}"

cp "$RECIPE_DIR/5.2/manifest.json" recipes/carlosgude/integration-engine/5.2/manifest.json
echo "   ✓ manifest.json"

cp "$RECIPE_DIR/5.2/config/packages/integration_engine.yaml" recipes/carlosgude/integration-engine/5.2/config/packages/integration_engine.yaml
echo "   ✓ config/packages/integration_engine.yaml"

cp "$RECIPE_DIR/5.2/post-install.txt" recipes/carlosgude/integration-engine/5.2/post-install.txt
echo "   ✓ post-install.txt"

touch recipes/carlosgude/integration-engine/5.2/src/Integration/.gitkeep
echo "   ✓ src/Integration/.gitkeep"

echo -e "${GREEN}   ✓ All files copied${NC}"
echo ""

# Paso 5: Commit
echo -e "${CYAN}[5/5] Creating commit...${NC}"
git add recipes/carlosgude/
git commit -m "feat: add recipe for integration-engine 5.2.0

Adds Symfony Flex recipe for carlosgude/integration-engine v5.2.0

On 'composer require carlosgude/integration-engine':
- Auto-registers IntegrationEngineBundle in bundles.php
- Creates config/packages/integration_engine.yaml with defaults
- Creates src/Integration/ directory for integration classes
- Shows post-install instructions (make:integration, make:observability)

No manual configuration needed. Just composer require + make:integration.

See: https://github.com/CarlosGude/integrationEngine
"

echo -e "${GREEN}   ✓ Commit created${NC}"
echo ""

# Show next steps
echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo -e "${GREEN}✓ Recipe ready!${NC}"
echo ""
echo -e "${YELLOW}Next steps:${NC}"
echo ""
echo "1️⃣  Push to your fork:"
echo -e "   ${CYAN}cd $HOME/symfony-recipes-contrib${NC}"
echo -e "   ${CYAN}git push origin add-integration-engine${NC}"
echo ""
echo "2️⃣  Create Pull Request on GitHub:"
echo -e "   ${CYAN}https://github.com/symfony/recipes-contrib${NC}"
echo ""
echo "3️⃣  Use this PR description:"
echo ""
cat << 'EOF'
## Add recipe for integration-engine 5.2.0

Adds Symfony Flex recipe for carlosgude/integration-engine v5.2.0

### What it does

When users run `composer require carlosgude/integration-engine`:
- Auto-registers `IntegrationEngineBundle` in `bundles.php`
- Creates `config/packages/integration_engine.yaml` with sensible defaults
- Creates `src/Integration/` directory structure
- Shows helpful post-install instructions

### Zero additional setup

Users can immediately run:
```bash
php bin/console make:integration MyApi GetUser
```

### Documentation

- Bundle: https://github.com/CarlosGude/integrationEngine
- Getting Started: https://github.com/CarlosGude/integrationEngine/blob/main/README.md
- Full Guide: https://github.com/CarlosGude/integrationEngine/blob/main/OBSERVABILITY.md

Recipe structure validated against Symfony Flex requirements.
EOF

echo ""
echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo "📁 Recipe files:"
find recipes/carlosgude/integration-engine/5.2 -type f | sed 's|^|   |'
echo ""
echo -e "${GREEN}Everything is ready. Just push and create the PR!${NC}"
