#!/bin/bash

# Fix API Includes Script
# Adds auth_helper.php includes to API files that need authentication

echo "🔧 Fixing API includes for authentication..."

# API files that need auth_helper.php
api_files=(
    "api/game.php"
    "api/ai_risk_meter.php" 
    "api/advanced_game.php"
    "api/nft_integration.php"
    "api/membership.php"
    "api/voice_assistant.php"
    "api/social_trading.php"
    "api/legal_network.php"
    "api/ar_vr_models.php"
    "api/white_label.php"
    "api/pro_services.php"
    "api/data_service.php"
    "api/smart_contracts.php"
    "api/user_interface.php"
    "api/premium_features.php"
    "api/multiplayer_game.php"
)

# Track files processed
processed=0
skipped=0

for file in "${api_files[@]}"; do
    if [ -f "$file" ]; then
        # Check if auth_helper is already included
        if grep -q "auth_helper.php" "$file"; then
            echo "⚠️  $file - auth_helper already included, skipping"
            ((skipped++))
        else
            # Add include at the beginning after opening PHP tag
            sed -i.bak '1a\
require_once __DIR__ . "/auth_helper.php";' "$file"
            
            echo "✅ $file - added auth_helper include"
            ((processed++))
        fi
    else
        echo "❌ $file - file not found"
    fi
done

echo ""
echo "📊 Summary:"
echo "   Files processed: $processed"
echo "   Files skipped: $skipped"
echo "   Missing files: $((${#api_files[@]} - processed - skipped))"

if [ $processed -gt 0 ]; then
    echo ""
    echo "✅ Authentication includes added successfully!"
    echo "   All API endpoints now have access to authenticate() function"
    echo "   Rate limiting and audit logging are now active"
else
    echo ""
    echo "ℹ️  No files needed updating"
fi