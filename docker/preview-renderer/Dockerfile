FROM mcr.microsoft.com/playwright:v1.63.0-noble

WORKDIR /opt/live-preview
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts
COPY tools/live-preview-renderer.mjs ./renderer.mjs

USER pwuser
EXPOSE 3100
HEALTHCHECK --interval=20s --timeout=3s --start-period=20s --retries=3 CMD node -e "require('http').get('http://127.0.0.1:3100/health',r=>process.exit(r.statusCode===204?0:1)).on('error',()=>process.exit(1))"
CMD ["node", "renderer.mjs"]
