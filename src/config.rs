use serde::Deserialize;
use std::fs;
use std::path::PathBuf;

extern crate num_cpus;


#[derive(Debug, Deserialize)]
pub struct Pool {
    #[serde(default = "Pool::default_workers")]
    pub workers: u32,
    #[serde(default = "Pool::default_interpreter")]
    pub interpreter: String,
    pub command: String,
}

impl Pool {
    pub fn cmd(&self) -> PathBuf {
        fs::canonicalize(self.command.as_str()).unwrap()
    }

    fn default_workers() -> u32 {
        num_cpus::get() as u32
    }

    fn default_interpreter() -> String {
        "/usr/bin/php".into()
    }
}

#[derive(Debug, Deserialize)]
pub struct Server {
    pub address: String,
}

impl Default for Server {
    fn default() -> Self {
        Server {
            address: "http://localhost:6300".into(),
        }
    }
}

#[derive(Debug, Deserialize)]
pub struct Config {
    #[serde(default)]
    pub server: Server,
    pub pool: Pool,
}

impl Config {
    pub fn new(path: String) -> Option<Config> {
        let path = fs::canonicalize(path).unwrap();

        if !path.exists() {
            ()
        }

        let ext = path.extension().unwrap().to_str().unwrap();

        let config : Option<Config> = match ext {
            "toml" => {
                let content = fs::read(path.to_path_buf()).unwrap();

                Some(toml::from_slice(content.as_slice()).unwrap())
            }
            "yaml" => {
                None
            }
            "json" => {
                None
            }
            _ => {
                None
            }
        };

        config
    }
}
