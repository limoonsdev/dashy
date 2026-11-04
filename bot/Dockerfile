FROM node:24-alpine

WORKDIR /app

COPY package.json ./
COPY src ./src
COPY scripts ./scripts

ENV NODE_ENV=production
EXPOSE 8787

USER node

HEALTHCHECK --interval=30s --timeout=3s --start-period=5s \
    CMD node -e "fetch('http://127.0.0.1:8787/health').then(r=>process.exit(r.ok?0:1)).catch(()=>process.exit(1))"

CMD ["node", "src/server.js"]
