const fs = require('fs');
const content = fs.readFileSync('public/tickets.php', 'utf8');

// Extract all script blocks
const scriptRegex = /<script>([\s\S]*?)<\/script>/g;
let match;
let lastScript = '';
while ((match = scriptRegex.exec(content)) !== null) {
    lastScript = match[1];
}

// Remove PHP tags
const cleaned = lastScript.replace(/<\?php[\s\S]*?\?>/g, '"__PHP__"');

try {
    new Function(cleaned);
    console.log('JS Syntax OK');
} catch (e) {
    console.log('JS Syntax Error:', e.message);
    // Find where the error is
    const lines = cleaned.split('\n');
    for (let i = 0; i < lines.length; i++) {
        if (lines[i].includes('{') || lines[i].includes('}')) {
            // Count braces
        }
    }
}
