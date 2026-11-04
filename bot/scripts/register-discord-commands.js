import { discordCommandDefinitions } from '../src/platforms/discord.js';

const applicationId = process.env.DISCORD_APPLICATION_ID;
const botToken = process.env.DISCORD_BOT_TOKEN;

if (!applicationId || !botToken) {
    console.error('Set DISCORD_APPLICATION_ID and DISCORD_BOT_TOKEN first.');
    process.exit(1);
}

const response = await fetch(`https://discord.com/api/v10/applications/${applicationId}/commands`, {
    method: 'PUT',
    headers: {
        'content-type': 'application/json',
        authorization: `Bot ${botToken}`,
    },
    body: JSON.stringify(discordCommandDefinitions()),
});

if (!response.ok) {
    console.error(`Discord returned HTTP ${response.status}: ${await response.text()}`);
    process.exit(1);
}

const commands = await response.json();
console.log(`Registered ${commands.length} commands.`);
