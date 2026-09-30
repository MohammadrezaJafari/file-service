# Quasar SPA built with Vite and served by nginx
FROM node:22-alpine AS build
WORKDIR /app
ARG API_URL=http://localhost:8000/api/v1
ENV API_URL=$API_URL
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npx quasar build

FROM nginx:alpine
COPY --from=build /app/dist/spa /usr/share/nginx/html
COPY nginx.conf /etc/nginx/conf.d/default.conf
EXPOSE 80
