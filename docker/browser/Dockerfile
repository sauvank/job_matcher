FROM mcr.microsoft.com/playwright:v1.49.0-noble

WORKDIR /app

COPY docker/browser/package.json ./
RUN npm install --omit=dev

COPY docker/browser/server.mjs ./

ENV PORT=3000
EXPOSE 3000

CMD ["node", "server.mjs"]
