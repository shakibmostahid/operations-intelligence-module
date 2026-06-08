FROM node:22-bookworm AS assets

WORKDIR /app

COPY codes /app

RUN npm ci \
    && npm run build

FROM nginx:1.27-alpine

COPY docker/configs/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=assets /app/public /var/www/html/public
