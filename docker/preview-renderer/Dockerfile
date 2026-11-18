FROM mcr.microsoft.com/playwright:v1.63.0-noble

WORKDIR /opt/live-preview
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts
COPY tools/live-preview-renderer.mjs ./renderer.mjs

USER pwuser
EXPOSE 3100
CMD ["node", "renderer.mjs"]
