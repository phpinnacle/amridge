use hyper::service::{make_service_fn, service_fn};
use hyper::{Server, Error, Response, Body, Request};
use std::sync::Arc;

use crate::handler::Handler;
use crate::process::ProcessPool;

mod process;
mod handler;

#[tokio::main]
async fn main() {
    let cmd = "/usr/bin/php".to_string();
    let args = vec!["/data/Development/phpinnacle/amridge/examples/http/worker.php".to_string()];
    let pool = ProcessPool::create(4, cmd, args).await.unwrap();

    let handler = Arc::new(Handler::create(pool));

    let service = make_service_fn(move |_conn| {
        let client = handler.clone();

        async move { Ok::<_, Error>(service_fn(move |req| proxy(client.clone(), req))) }
    });

    let addr = ([127, 0, 0, 1], 3000).into();
    let server = Server::bind(&addr).serve(service);

    if let Err(e) = server.await {
        eprintln!("server error: {}", e);
    }
}

async fn proxy(client: Arc<Handler>, req: Request<Body>) -> Result<Response<Body>, Error> {
    client.handle(req).await
}
