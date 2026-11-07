FROM nginx:alpine

# Remove default config
RUN rm /etc/nginx/conf.d/default.conf

# Copy our config
COPY docker/web/nginx.conf /etc/nginx/conf.d/default.conf

# Copy site files
COPY web/index.html /usr/share/nginx/html/
COPY web/css/ /usr/share/nginx/html/css/
COPY web/js/ /usr/share/nginx/html/js/
COPY web/docs/ /usr/share/nginx/html/docs/
COPY web/config.js /usr/share/nginx/html/

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
