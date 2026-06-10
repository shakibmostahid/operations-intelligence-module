FROM node:22-bookworm AS assets

WORKDIR /app

COPY codes/package.json codes/package-lock.json ./

RUN npm ci

COPY codes /app

RUN rm -f public/hot public/fonts-manifest.dev.json \
    && npm run build \
    && rm -f public/hot public/fonts-manifest.dev.json

FROM nginx:1.27-alpine

COPY docker/configs/nginx.conf /etc/nginx/conf.d/default.conf
COPY docker/entrypoints/nginx-entrypoint.sh /usr/local/bin/app-nginx-entrypoint
COPY --from=assets /app/public /var/www/html/public
COPY --from=assets /app/public/build /opt/app-build

RUN chmod +x /usr/local/bin/app-nginx-entrypoint

ENTRYPOINT ["app-nginx-entrypoint"]
CMD ["nginx", "-g", "daemon off;"]
